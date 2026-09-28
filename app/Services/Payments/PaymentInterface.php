<?php

namespace App\Services\Payments;

interface PaymentInterface
{
    public function createPayment(float $amount, string $currency, array $metadata = []): array;
    public function getPaymentStatus(string $paymentId): array;
    public function processWebhook(array $payload): array;
    public function refundPayment(string $paymentId, float $amount = null): array;
    public function verifyWebhookSignature(array $payload, string $signature): bool;
    public function getProviderName(): string;
}