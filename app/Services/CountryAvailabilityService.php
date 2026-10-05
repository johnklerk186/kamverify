<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Provider;
use App\Models\ProviderCountry;
use App\Models\Service;
use App\Models\ServiceCountry;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the per-service country list for the buy flow.
 *
 * A country appears for a service only when every layer agrees:
 *   - the service is storefront-enabled
 *   - the country is active and has an active provider mapping
 *   - the provider currently reports allocatable stock (when it can
 *     answer — on provider failure we fall back to mappings and let
 *     the live quote gate each purchase, so a flaky ranking call can
 *     never take the whole catalogue offline)
 *   - the admin has not disabled that service+country pair
 *
 * Popular chips are admin-configured per service; when none are set,
 * the highest-stock countries are promoted automatically.
 */
class CountryAvailabilityService
{
    public const CACHE_TTL = 300; // 5 min — underlying provider data is itself cached 90s

    public function __construct(protected ProviderService $providerService) {}

    public function cacheKey(Service $service): string
    {
        return "service_countries:{$service->id}";
    }

    public function forget(Service $service): void
    {
        Cache::forget($this->cacheKey($service));
    }

    /**
     * Customer-facing list. Cached briefly; the purchase-time quote
     * re-verifies availability live so a stale entry can only ever
     * produce a "not available" quote, never a failed charge.
     */
    public function forService(Service $service): array
    {
        return Cache::remember($this->cacheKey($service), self::CACHE_TTL,
            fn () => $this->build($service));
    }

    /**
     * Admin view — uncached, includes service-disabled countries so
     * they can be re-enabled, and reports stock where the provider
     * answered.
     */
    public function forServiceAdmin(Service $service): array
    {
        return $this->build($service, true);
    }

    protected function build(Service $service, bool $includeDisabled = false): array
    {
        if (!$service->is_active || !$service->customer_enabled || $service->temporarily_unavailable) {
            return ['count' => 0, 'popular' => [], 'countries' => []];
        }

        // Route per-service — facebook resolves to TextVerified when
        // its provider_mapping override is set; others stay on HeroSMS.
        $provider = $this->providerService->providerFor($service);
        if (!$provider || !$provider->is_active) {
            return ['count' => 0, 'popular' => [], 'countries' => []];
        }

        $serviceMap = $provider->providerServices()
            ->where('service_id', $service->id)
            ->where('is_active', true)
            ->first();
        if (!$serviceMap) {
            return ['count' => 0, 'popular' => [], 'countries' => []];
        }

        // Live allocatable stock keyed by provider country ID — or null
        // when the provider couldn't answer (fall back to mappings).
        $counts = null;
        try {
            $counts = $this->providerService
                ->getProviderForModel($provider)
                ->availableCountryCounts($service->slug);
        } catch (\Throwable $e) {
            $counts = null;
        }

        // Active country mappings for this provider → Country models
        $mappings = ProviderCountry::where('provider_id', $provider->id)
            ->where('is_active', true)
            ->with('country')
            ->get()
            ->filter(fn ($m) => $m->country && $m->country->is_active);

        $overrides = ServiceCountry::where('service_id', $service->id)->get()
            ->keyBy('country_id');

        $countries = [];
        foreach ($mappings as $mapping) {
            $country = $mapping->country;
            $override = $overrides->get($country->id);
            $enabled = !($override && $override->is_enabled === false);

            if (!$includeDisabled && !$enabled) {
                continue;
            }

            $stock = $counts === null
                ? null // provider silent → mapping-only availability
                : ($counts[(int) $mapping->provider_country_code] ?? 0);

            if ($stock !== null && $stock <= 0) {
                continue; // provider says no allocatable numbers
            }

            $countries[] = [
                'id'        => $country->id,
                'name'      => $country->name,
                'code'      => $country->code,
                'dial_code' => $country->dial_code,
                'flag'      => countryFlag($country->code),
                'stock'     => $stock,
                'popular'   => (bool) ($override?->is_popular) || (bool) $country->is_popular,
                'enabled'   => $enabled,
            ];
        }

        // Popular countries first, then the rest — alphabetical
        // within each group, deterministic.
        usort($countries, fn ($a, $b) =>
            ($b['popular'] <=> $a['popular']) ?: strcmp($a['name'], $b['name']));

        // Popular chips: per-service pins first (in admin's order),
        // then globally-popular countries (alphabetical). When nothing
        // is configured, auto-promote the highest-stock countries.
        $popularIds = ServiceCountry::where('service_id', $service->id)
            ->where('is_popular', true)
            ->orderBy('popular_sort')
            ->pluck('country_id')
            ->filter(fn ($id) => collect($countries)->contains('id', $id))
            ->values()
            ->all();

        $globalPopular = collect($countries)
            ->filter(fn ($c) => $c['popular'] && !in_array($c['id'], $popularIds, true))
            ->sortBy('name')
            ->pluck('id')
            ->all();
        $popularIds = array_merge($popularIds, $globalPopular);

        if (empty($popularIds)) {
            $popularIds = collect($countries)
                ->sortByDesc(fn ($c) => $c['stock'] ?? 0)
                ->take(6)
                ->pluck('id')
                ->all();

            // Auto-promoted entries carry the badge/ordering too so
            // chips, badges and list order stay consistent.
            $countries = array_map(function ($c) use ($popularIds) {
                $c['popular'] = $c['popular'] || in_array($c['id'], $popularIds, true);
                return $c;
            }, $countries);
            usort($countries, fn ($a, $b) =>
                ($b['popular'] <=> $a['popular']) ?: strcmp($a['name'], $b['name']));
        }

        return [
            'count'     => count($countries),
            'popular'   => $popularIds,
            'countries' => $countries,
        ];
    }
}
