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
     * Two collection modes:
     *
     *  - direct-pay (default when a phone number is supplied): charges the
     *    customer's MoMo wallet directly — Fapshi pushes the approval
     *    prompt to the phone, no redirect. Requires amount + phone
     *    (9-digit Cameroon number); medium auto/fixed to 'mtn'.
     *  - initiate-pay (fallback, no phone): hosted checkout link the
     *    customer is redirected to. Links expire after 24h.
     *
     * $metadata may carry phone, medium, name, email, user_id,
     * external_id, redirect_url (initiate-pay only), message.
     */
    public function createPayment(float $amount, string $currency, array $metadata = []): array
    {
        if ($this->useMock) {
            return $this->mockCreatePayment($amount, $currency, $metadata);
        }

        $this->assertConfigured();

        $phone = isset($metadata['phone'])
            ? $this->normalizePhone((string) $metadata['phone'])
            : null;
        $direct = $phone !== null;

        $body = array_filter([
            'amount'      => (int) round($amount),
            'phone'       => $phone,
            'medium'      => $direct ? ($metadata['medium'] ?? 'mtn') : null,
            'name'        => $direct ? ($metadata['name'] ?? null) : null,
            'email'       => $metadata['email'] ?? null,
            'redirectUrl' => $direct ? null : ($metadata['redirect_url'] ?? null),
            'userId'      => isset($metadata['user_id']) ? (string) $metadata['user_id'] : null,
            'externalId'  => $metadata['external_id'] ?? null,
            'message'     => $metadata['message'] ?? 'KamVerify wallet deposit',
        ], fn ($v) => $v !== null);

        $endpoint = $direct ? '/direct-pay' : '/initiate-pay';

        $response = Http::timeout(30)
            ->withHeaders($this->authHeaders())
            ->post($this->baseUrl . $endpoint, $body);

        if ($response->failed()) {
            Log::error('Fapshi ' . ltrim($endpoint, '/') . ' failed', [
                'status' => $response->status(),
                'error'  => $response->json('message') ?? $response->body(),
            ]);
            throw new \Exception(
                $response->json('message') ?? 'The payment provider rejected the request.'
            );
        }

        $data = $response->json();

        if (empty($data['transId'])) {
            throw new \Exception('Fapshi did not return a transaction ID.');
        }

        if (!$direct && empty($data['link'])) {
            throw new \Exception('Fapshi did not return a payment link.');
        }

        return [
            'payment_id'   => $data['transId'],
            'status'       => 'pending',
            'amount'       => $amount,
            'currency'     => 'XAF',
            'redirect_url' => $direct ? null : $data['link'],
            'direct_pay'   => $direct,
            'initiated_at' => $data['dateInitiated'] ?? null,
        ];
    }

    /**
     * Accepts 6XXXXXXXX or 2376XXXXXXXX and returns the 9-digit
     * Cameroon MSISDN Fapshi expects.
     */
    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '237') && strlen($digits) === 12) {
            $digits = substr($digits, 3);
        }
        return $digits;
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
