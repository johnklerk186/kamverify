<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Service;
use App\Models\Setting;

class PricingService
{
    /**
     * Provider costs arrive in USD; customers pay in whole XAF. The
     * configured exchange rate converts the cost, then the markup is
     * applied and the result is rounded up to the nearest XAF.
     */
    public function calculateSellingPrice(float $providerCost, ?Country $country = null, ?Service $service = null): int
    {
        $baseXaf = usdToXaf($providerCost);
        $mode = $this->resolveMode($service);
        $markup = $this->getMarkup($country, $service, $mode);

        if ($mode === 'percentage') {
            return (int) ceil($baseXaf * (1 + $markup));
        }

        return $baseXaf + (int) round($markup);
    }

    /**
     * Pricing mode resolution: a service's own pricing_config['mode']
     * ('fixed'|'percentage') wins; otherwise the global default.
     * Default mode is fixed XAF markup.
     */
    public function resolveMode(?Service $service = null): string
    {
        $mode = $service?->pricing_config['mode'] ?? null;

        if (in_array($mode, ['fixed', 'percentage'], true)) {
            return $mode;
        }

        return Setting::get('default_markup_type', 'fixed') === 'percentage'
            ? 'percentage'
            : 'fixed';
    }

    public function getMarkup(?Country $country = null, ?Service $service = null, ?string $mode = null): float
    {
        $mode = $mode ?? $this->resolveMode($service);
        $normalize = fn ($v) => $mode === 'percentage' ? ((float) $v) / 100 : (float) $v;

        // Service-specific markup wins
        if ($service && isset($service->pricing_config['markup'])) {
            return $normalize($service->pricing_config['markup']);
        }

        // Then country-specific markup
        if ($country && isset($country->pricing_config['markup'])) {
            return $normalize($country->pricing_config['markup']);
        }

        return $this->getDefaultMarkupValue($mode);
    }

    /**
     * Default markup read from admin-managed settings.
     * Percentage mode expects a fraction (0.30 = 30%).
     */
    protected function getDefaultMarkupValue(string $mode): float
    {
        $value = (float) Setting::get('default_markup_value', 0);

        // Store the setting as a human number (30 = 30% or 30 XAF fixed)
        if ($mode === 'percentage') {
            return $value / 100;
        }

        return $value;
    }

    /**
     * Profit in XAF: the XAF selling price minus the USD provider cost
     * converted at the configured rate.
     */
    public function calculateProfit(float $sellingPrice, float $purchasePrice): int
    {
        return (int) round($sellingPrice) - usdToXaf($purchasePrice);
    }
}
