<?php

namespace Tests\Concerns;

use App\Models\Country;
use App\Models\Provider;
use App\Models\ProviderCountry;
use App\Models\ProviderService;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;

trait SeedsMarketplace
{
    protected function seedMarketplace(): array
    {
        $provider = Provider::create([
            'name' => 'HeroSMS',
            'slug' => 'herosms',
            'base_url' => 'https://herosms.test/api',
            'is_active' => true,
        ]);

        $country = Country::create([
            'name' => 'United States',
            'code' => 'US',
            'dial_code' => '+1',
            'is_active' => true,
        ]);

        $service = Service::create([
            'name' => 'WhatsApp',
            'slug' => 'whatsapp',
            'icon' => 'whatsapp',
            'is_active' => true,
            'customer_enabled' => true,
        ]);

        ProviderService::create([
            'provider_id' => $provider->id,
            'service_id' => $service->id,
            'provider_service_code' => 'wa',
            'cost' => 0.50,
            'is_active' => true,
        ]);

        ProviderCountry::create([
            'provider_id' => $provider->id,
            'country_id' => $country->id,
            'provider_country_code' => 'US',
            'is_active' => true,
        ]);

        return [$provider, $country, $service];
    }

    protected function customer(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => 'customer',
            'is_active' => true,
        ], $overrides));

        Wallet::create([
            'user_id' => $user->id,
            'balance' => 50000,
            'total_deposited' => 50000,
            'total_withdrawn' => 0,
            'currency' => 'XAF',
            'is_active' => true,
        ]);

        return $user;
    }

    protected function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
