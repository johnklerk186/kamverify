<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\ProviderService;
use App\Models\Service;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\PricingService;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function __construct(
        protected PricingService $pricingService,
        protected AuditService $auditService
    ) {}

    public function index()
    {
        $services = Service::orderByDesc('customer_enabled')->orderBy('name')->get()
            ->map(function ($service) {
                $service->provider_cost = ProviderService::where('service_id', $service->id)
                    ->where('is_active', true)->min('cost');
                $service->markup = $service->pricing_config['markup'] ?? null;
                $service->mode = $service->pricing_config['mode'] ?? null;
                $service->resolved_mode = $this->pricingService->resolveMode($service);
                $service->customer_price = $service->provider_cost !== null
                    ? $this->pricingService->calculateSellingPrice((float) $service->provider_cost, null, $service)
                    : null;
                return $service;
            });

        $countries = Country::orderBy('name')->get()->map(function ($country) {
            $country->markup = $country->pricing_config['markup'] ?? null;
            return $country;
        });

        $defaults = [
            'markup_type' => Setting::get('default_markup_type', 'percentage'),
            'markup_value' => Setting::get('default_markup_value', 30),
        ];

        return view('admin.pricing.index', compact('services', 'countries', 'defaults'));
    }

    public function updateDefaults(Request $request)
    {
        $validated = $request->validate([
            'markup_type' => 'required|in:percentage,fixed',
            'markup_value' => 'required|numeric|min:0',
        ]);

        Setting::set('default_markup_type', $validated['markup_type'], 'string', 'pricing');
        Setting::set('default_markup_value', (string) $validated['markup_value'], 'float', 'pricing');

        $this->auditService->log('pricing.defaults.update', null, null, $validated);

        return back()->with('success', 'Default pricing updated.');
    }

    public function updateService(Request $request, Service $service)
    {
        $validated = $request->validate([
            'markup' => 'nullable|numeric|min:0|max:10000000',
            'mode' => 'nullable|in:fixed,percentage',
            'provider_cost' => 'nullable|numeric|min:0',
        ]);

        $oldConfig = $service->pricing_config ?? [];
        $config = $oldConfig;

        if ($request->filled('markup')) {
            $config['markup'] = (float) $validated['markup'];
        } else {
            unset($config['markup']);
        }

        if ($request->filled('mode')) {
            $config['mode'] = $validated['mode'];
        } else {
            unset($config['mode']);
        }

        $service->update(['pricing_config' => $config]);

        // Keep provider cost mapping in sync when provided
        if ($request->filled('provider_cost')) {
            ProviderService::where('service_id', $service->id)
                ->update(['cost' => (float) $validated['provider_cost']]);
        }

        $this->auditService->log('pricing.service.update', $service, $oldConfig, $config);

        return back()->with('success', "Pricing for {$service->name} updated.");
    }

    public function updateCountry(Request $request, Country $country)
    {
        $validated = $request->validate([
            'markup' => 'nullable|numeric|min:0',
        ]);

        $config = $country->pricing_config ?? [];
        if ($request->filled('markup')) {
            $config['markup'] = (float) $validated['markup'];
        } else {
            unset($config['markup']);
        }

        $country->update(['pricing_config' => $config]);

        $this->auditService->log('pricing.country.update', $country, null, $validated);

        return back()->with('success', "Pricing for {$country->name} updated.");
    }
}
