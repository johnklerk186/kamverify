<?php

use App\Models\Country;
use App\Models\Provider;
use App\Models\ProviderCountry;
use App\Models\ProviderService;
use App\Models\Service;
use Illuminate\Database\Migrations\Migration;

/**
 * Production wiring for the TextVerified provider. This lives in a
 * migration (not only the seeder) so it applies on every deploy —
 * the seeder is not run on the production host.
 *
 * Result:
 *   - providers row "textverified"
 *   - provider_services:  textverified × facebook (service_name 'facebook')
 *   - provider_countries: textverified × US
 *   - services.provider_mapping = {provider: textverified, countries: [US]}
 *     so facebook+US routes to TextVerified and every other Facebook
 *     country keeps its existing HeroSMS mapping.
 *
 * Idempotent — safe to run repeatedly. Facebook's temporarily_unavailable
 * flag and HeroSMS mappings are left untouched; the outage toggle still
 * controls customer-facing availability.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tv = Provider::firstOrCreate(
            ['slug' => 'textverified'],
            [
                'name' => 'TextVerified',
                'base_url' => 'https://www.textverified.com',
                'is_active' => true,
                'config' => ['timeout' => 30],
            ]
        );

        $facebook = Service::where('slug', 'facebook')->first();
        if (!$facebook) {
            return;
        }

        ProviderService::firstOrCreate(
            ['provider_id' => $tv->id, 'service_id' => $facebook->id],
            ['provider_service_code' => 'facebook', 'cost' => 0, 'is_active' => true]
        );

        $us = Country::where('code', 'US')->first();
        if ($us) {
            ProviderCountry::firstOrCreate(
                ['provider_id' => $tv->id, 'country_id' => $us->id],
                ['provider_country_code' => 'US', 'is_active' => true]
            );
        }

        $facebook->update(['provider_mapping' => [
            'provider' => 'textverified',
            'countries' => ['US'],
        ]]);
    }

    public function down(): void
    {
        $facebook = Service::where('slug', 'facebook')->first();
        if ($facebook && ($facebook->provider_mapping['provider'] ?? null) === 'textverified') {
            $facebook->update(['provider_mapping' => null]);
        }

        $tv = Provider::where('slug', 'textverified')->first();
        if (!$tv) {
            return;
        }

        // Only remove the registration if nothing real was fulfilled
        // through it — never orphan order history.
        if (\App\Models\Order::where('provider_id', $tv->id)->exists()) {
            return;
        }

        ProviderCountry::where('provider_id', $tv->id)->delete();
        ProviderService::where('provider_id', $tv->id)->delete();
        $tv->delete();
    }
};
