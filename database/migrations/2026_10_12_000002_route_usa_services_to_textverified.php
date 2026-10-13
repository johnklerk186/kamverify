<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Route EVERY service's US traffic to TextVerified (previously only
 * facebook+US used it; HeroSMS keeps every non-US country).
 *
 * Three data layers, all idempotent:
 *   - provider_countries: textverified → US (purchase/country mapping)
 *   - provider_services:  textverified × every service, with
 *     provider_service_code seeded from our slug — admins can correct
 *     any TextVerified service-name mismatch from the provider edit
 *     page without a deploy
 *   - services.provider_mapping: {provider: 'textverified',
 *     countries: ['US']} — country-scoped routing override resolved
 *     by ProviderService::providerFor; non-US countries still fall
 *     through to the HeroSMS mappings
 */
return new class extends Migration
{
    public function up(): void
    {
        $tv = DB::table('providers')->where('slug', 'textverified')->first();
        $us = DB::table('countries')->where('code', 'US')->first();
        if (!$tv || !$us) {
            return;
        }

        $now = now();
        DB::table('provider_countries')->updateOrInsert(
            ['provider_id' => $tv->id, 'country_id' => $us->id],
            ['provider_country_code' => 'US', 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]
        );

        foreach (DB::table('services')->get() as $service) {
            $mapping = DB::table('provider_services')
                ->where('provider_id', $tv->id)->where('service_id', $service->id)->first();
            if ($mapping) {
                DB::table('provider_services')->where('id', $mapping->id)
                    ->update(['is_active' => true, 'updated_at' => $now]);
            } else {
                DB::table('provider_services')->insert([
                    'provider_id' => $tv->id, 'service_id' => $service->id,
                    'provider_service_code' => $service->slug,
                    'cost' => 0, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            DB::table('services')->where('id', $service->id)->update([
                'provider_mapping' => json_encode(['provider' => 'textverified', 'countries' => ['US']]),
            ]);
        }
    }

    public function down(): void
    {
        $tv = DB::table('providers')->where('slug', 'textverified')->first();
        if (!$tv) {
            return;
        }

        // Restore the pre-rollout state: TextVerified kept only facebook+US.
        foreach (DB::table('services')->get() as $service) {
            DB::table('services')->where('id', $service->id)->update([
                'provider_mapping' => $service->slug === 'facebook'
                    ? json_encode(['provider' => 'textverified', 'countries' => ['US']])
                    : null,
            ]);
        }

        DB::table('provider_services')->where('provider_id', $tv->id)
            ->whereNotIn('service_id',
                DB::table('services')->where('slug', 'facebook')->pluck('id'))
            ->delete();
    }
};
