<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real Fapshi API client (https://docs.fapshi.com).
 *
 * Auth:   apiuser / apikey request headers on every call.
 * Pay in: POST /initiate-pay → returns a hosted-checkout {link, transId}
 *                             the customer is redirected to. Links expire
 *                             after 24h (Fapshi reports EXPIRED).
 * Status: GET  /payment-status/{transId} → CREATED|PENDING|SUCCESSFUL|
 *                                          FAILED|EXPIRED (6 req/min cap).
 * Refund: POST /payout is disbursement-side — wallet refunds are handled
 *         internally by KamVerify, not via Fapshi.
 *
 * Base URLs: https://sandbox.fapshi.com  (FAPSHI_MODE=sandbox)
 *            https://live.fapshi.com     (FAPSHI_MODE=live)
 */
class FapshiPaymentProvider implements PaymentInterface
{
    protected string $baseUrl;
    protected string $apiUser;
    protected string $apiKey;
    protected string $mode;
    protected bool $useMock = false;

    public function __construct()
    {
        $this->mode    = (string) config('services.fapshi.mode', env('FAPSHI_MODE', 'sandbox'));
        $this->baseUrl = $this->mode === 'live'
            ? 'https://live.fapshi.com'
            : 'https://sandbox.fapshi.com';
        $this->apiUser = (string) config('services.fapshi.api_user', env('FAPSHI_API_USER', ''));
        $this->apiKey  = (string) config('services.fapshi.api_key', env('FAPSHI_API_KEY', ''));
        $this->useMock = filter_var(
            config('services.fapshi.use_mock', env('FAPSHI_USE_MOCK', true)),
            FILTER_VALIDATE_BOOL
        );

        // Safety rail: mock payments must never run in production.
        if ($this->useMock && app()->environment('production')) {
            throw new \RuntimeException(
                'Fapshi mock payment provider is enabled in production. Set FAPSHI_USE_MOCK=false and configure real credentials.'
            );
        }
    }

    /**
     * Fapshi initiate-pay: creates a hosted checkout link the customer is
     * redirected to. Requires amount (int XAF, min 100). $metadata may carry
     * redirect_url, email, user_id, external_id, message.
     */
    public function createPayment(float $amount, string $currency, array $metadata = []): array
    {
        if ($this->useMock) {
            return $this->mockCreatePayment($amount, $currency, $metadata);
        }

        $this->assertConfigured();

        $response = Http::timeout(30)
            ->withHeaders($this->authHeaders())
            ->post($this->baseUrl . '/initiate-pay', array_filter([
                'amount'      => (int) round($amount),
                'email'       => $metadata['email'] ?? null,
                'redirectUrl' => $metadata['redirect_url'] ?? null,
                'userId'      => isset($metadata['user_id']) ? (string) $metadata['user_id'] : null,
                'externalId'  => $metadata['external_id'] ?? null,
                'message'     => $metadata['message'] ?? 'KamVerify wallet deposit',
            ], fn ($v) => $v !== null));

        if ($response->failed()) {
            Log::error('Fapshi initiate-pay failed', [
                'status' => $response->status(),
                'error'  => $response->json('message') ?? $response->body(),
            ]);
            throw new \Exception(
                $response->json('message') ?? 'The payment provider rejected the request.'
            );
        }

        $data = $response->json();

        if (empty($data['transId']) || empty($data['link'])) {
            throw new \Exception('Fapshi did not return a payment link.');
        }

        return [
            'payment_id'   => $data['transId'],
            'status'       => 'pending',
            'amount'       => $amount,
            'currency'     => 'XAF',
            'redirect_url' => $data['link'],
            'initiated_at' => $data['dateInitiated'] ?? null,
        ];
    }

    /**
     * GET /payment-status/{transId} — normalised to the internal status
     * vocabulary used by PaymentService.
     */
    public function getPaymentStatus(string $paymentId): array
    {
        if ($this->useMock) {
            return $this->mockGetPaymentStatus($paymentId);
        }

        $this->assertConfigured();

        $response = Http::timeout(30)
            ->withHeaders($this->authHeaders())
            ->get($this->baseUrl . '/payment-status/' . $paymentId);

        if ($response->failed()) {
            Log::error('Fapshi payment-status failed', [
                'status' => $response->status(),
                'trans_id' => $paymentId,
            ]);
            return [];
        }

        $data = $response->json();

        return [
            'payment_id' => $data['transId'] ?? $paymentId,
            'external_id' => $data['externalId'] ?? null,
            'status'     => strtolower((string) ($data['status'] ?? 'unknown')), // successful→normalized below
            'amount'     => $data['amount'] ?? null,
            'revenue'    => $data['revenue'] ?? null,
            'medium'     => $data['medium'] ?? null,
            'financial_trans_id' => $data['financialTransId'] ?? null,
        ];
    }

    /**
     * Fapshi webhooks POST the transaction object to the URL configured
     * on the service. The payload is only used to locate our payment —
     * PaymentService re-verifies via getPaymentStatus before crediting.
     */
    public function processWebhook(array $payload): array
    {
        return [
            'payment_id'  => $payload['transId'] ?? null,
            'external_id' => $payload['externalId'] ?? null,
            'status'      => strtolower((string) ($payload['status'] ?? 'unknown')),
            'amount'      => $payload['amount'] ?? null,
        ];
    }

    /**
     * Deposits are not refundable through Fapshi's collection API.
     * Refunds stay internal (wallet ledger) or use the payout endpoint —
     * intentionally unsupported here rather than invented.
     */
    public function refundPayment(string $paymentId, float $amount = null): array
    {
        throw new \Exception('Fapshi collection refunds are not supported by the API — resolve via support or the payout endpoint.');
    }

    /**
     * Fapshi does not sign webhooks; authenticity is established by
     * re-fetching the transaction status from the API (see
     * PaymentService::verifyAndApplyStatus).
     */
    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return true;
    }

    public function getProviderName(): string
    {
        return 'Fapshi';
    }

    protected function authHeaders(): array
    {
        return [
            'apiuser' => $this->apiUser,
            'apikey'  => $this->apiKey,
            'Accept'  => 'application/json',
        ];
    }

    protected function assertConfigured(): void
    {
        if ($this->apiUser === '' || $this->apiKey === '') {
            throw new \RuntimeException(
                'Fapshi credentials are not configured. Set FAPSHI_API_USER and FAPSHI_API_KEY in .env.'
            );
        }
    }

    // ---- Mock methods for automated tests / local development ----

    protected function mockCreatePayment(float $amount, string $currency, array $metadata = []): array
    {
        Log::info('Mock Fapshi payment created', [
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return [
            'payment_id' => 'FAP-' . strtoupper(uniqid()),
            'status' => 'pending',
            'amount' => $amount,
            'currency' => 'XAF',
        ];
    }

    protected function mockGetPaymentStatus(string $paymentId): array
    {
        return [
            'payment_id' => $paymentId,
            'status' => 'completed',
            'amount' => 0,
            'currency' => 'XAF',
        ];
    }
}
