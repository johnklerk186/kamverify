<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Log;

class MockPaymentProvider implements PaymentInterface
{
    public function createPayment(float $amount, string $currency, array $metadata = []): array
    {
        Log::info('Mock payment created', [
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => $metadata,
        ]);

        return [
            'payment_id' => 'PAY-' . strtoupper(uniqid()),
            'status' => 'success',
            'amount' => $amount,
            'currency' => $currency,
            'redirect_url' => null,
            'checkout_url' => null,
        ];
    }

    public function getPaymentStatus(string $paymentId): array
    {
        Log::info('Mock payment status check', ['payment_id' => $paymentId]);

        return [
            'payment_id' => $paymentId,
            'status' => 'completed',
            'amount' => 0,
            'currency' => 'USD',
        ];
    }

    public function processWebhook(array $payload): array
    {
        Log::info('Mock webhook processed', ['payload' => $payload]);

        return [
            'payment_id' => $payload['payment_id'] ?? null,
            'status' => 'completed',
            'amount' => $payload['amount'] ?? 0,
        ];
    }

    public function refundPayment(string $paymentId, float $amount = null): array
    {
        Log::info('Mock payment refund', ['payment_id' => $paymentId, 'amount' => $amount]);

        return [
            'payment_id' => $paymentId,
            'refund_id' => 'REF-' . strtoupper(uniqid()),
            'status' => 'success',
            'amount' => $amount,
        ];
    }

    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        return true; // Always return true for mock
    }

    public function getProviderName(): string
    {
        return 'Mock Payment';
    }
}