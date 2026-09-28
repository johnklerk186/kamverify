<?php

namespace App\Services\Providers;

interface ProviderInterface
{
    public function getBalance(): float;
    public function getCountries(): array;
    public function getServices(): array;
    public function getAvailableNumbers(string $countryCode, string $serviceSlug): array;

    /**
     * Allocatable stock per provider country ID for a service:
     * [providerCountryId => count]. Null when the provider cannot
     * answer — callers should fall back to their own mappings.
     */
    public function availableCountryCounts(string $serviceSlug): ?array;
    public function purchaseNumber(string $countryCode, string $serviceSlug, array $options = []): array;
    public function getActivationStatus(string $activationId): array;
    public function getSms(string $activationId): array;
    public function cancelActivation(string $activationId): array;
    public function requestRefund(string $activationId): array;
    public function isActive(): bool;
    public function getProviderName(): string;
}