<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Provider;
use App\Models\Service;
use App\Services\Providers\HeroSmsProvider;
use App\Services\Providers\ProviderInterface;
use App\Services\Providers\TextVerifiedProvider;

class ProviderService
{
    protected array $providers = [];

    public function __construct()
    {
        $this->registerProvider('hero_sms', new HeroSmsProvider());
        $this->registerProvider('herosms', $this->providers['hero_sms']);
        $this->registerProvider('textverified', new TextVerifiedProvider());
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

    /**
     * Which provider model fulfils a service (+country)?
     *
     * Resolution order:
     *  1. Explicit routing override — services.provider_mapping.provider
     *     (e.g. facebook → "textverified"). Returned even when the
     *     provider row is inactive so callers can fail closed with a
     *     clear message rather than silently falling back.
     *  2. Active provider_services (+ provider_countries) mappings.
     *  3. First active provider — the historical default: before routing
     *     existed, every purchase went to the single active provider.
     *     Unmapped services keep working exactly as they did.
     */
    public function providerFor(Service $service, ?Country $country = null): ?Provider
    {
        $slug = $service->provider_mapping['provider'] ?? null;
        if ($slug) {
            return Provider::where('slug', $slug)->first();
        }

        $query = Provider::where('is_active', true)
            ->whereHas('providerServices', fn ($q) => $q
                ->where('service_id', $service->id)
                ->where('is_active', true));

        if ($country) {
            $query->whereHas('providerCountries', fn ($q) => $q
                ->where('country_id', $country->id)
                ->where('is_active', true));
        }

        return $query->orderBy('id')->first()
            ?? Provider::where('is_active', true)->orderBy('id')->first();
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
