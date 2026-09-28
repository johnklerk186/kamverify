<?php

namespace App\Services;

use App\Services\Providers\HeroSmsProvider;
use App\Services\Providers\ProviderInterface;
use App\Models\Provider;

class ProviderService
{
    protected array $providers = [];

    public function __construct()
    {
        $this->registerProvider('hero_sms', new HeroSmsProvider());
    }

    public function registerProvider(string $name, ProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    public function getProvider(string $name): ?ProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    public function getDefaultProvider(): ProviderInterface
    {
        return $this->getProvider('hero_sms') ?? throw new \Exception('No default provider configured');
    }

    public function getProviderForModel(Provider $providerModel): ProviderInterface
    {
        return $this->getProvider($providerModel->slug) ?? $this->getDefaultProvider();
    }

    public function getActiveProviders(): array
    {
        return array_filter($this->providers, fn($provider) => $provider->isActive());
    }

    public function getAllProviders(): array
    {
        return $this->providers;
    }
}