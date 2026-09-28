<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Services\ProviderService;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function __construct(
        protected ProviderService $providerService,
        protected AuditService $auditService
    ) {}

    public function index()
    {
        $providers = Provider::withCount(['providerServices', 'providerCountries', 'orders'])
            ->with(['providerServices.service', 'providerCountries.country'])
            ->get()
            ->map(function ($provider) {
                try {
                    $impl = $this->providerService->getProviderForModel($provider);
                    $provider->live_status = [
                        'configured' => $impl->isActive(),
                        'balance' => $impl->getBalance(),
                        'sandbox' => !$impl->isActive(),
                    ];
                } catch (\Exception $e) {
                    $provider->live_status = ['configured' => false, 'balance' => null, 'sandbox' => true];
                }
                return $provider;
            });

        return view('admin.providers.index', compact('providers'));
    }

    public function edit(Provider $provider)
    {
        $provider->load(['providerServices.service', 'providerCountries.country']);
        $services = \App\Models\Service::orderBy('name')->get();
        $countries = \App\Models\Country::orderBy('name')->get();

        // Activation stats from our own orders (the source of truth)
        $stats = [
            'total' => $provider->orders()->count(),
            'successful' => $provider->orders()->where('status', 'completed')->count(),
            'failed' => $provider->orders()->whereIn('status', ['failed', 'cancelled'])->count(),
            'active' => $provider->orders()->whereIn('status', ['pending', 'processing', 'number_assigned', 'waiting_for_sms', 'sms_received'])->count(),
        ];

        // Live balance — short cache so the page doesn't hammer the API.
        $liveBalance = null;
        $catalog = ['countries' => [], 'services' => []];
        try {
            $impl = $this->providerService->getProviderForModel($provider);
            if ($impl->isActive()) {
                $liveBalance = \Illuminate\Support\Facades\Cache::remember(
                    "provider_balance:{$provider->id}", 60, fn () => $impl->getBalance()
                );
                // Live catalog for mapping reference (cached 10 min)
                $catalog = \Illuminate\Support\Facades\Cache::remember(
                    "provider_catalog:{$provider->id}", 600, fn () => [
                        'countries' => $impl->getCountries(),
                        'services' => $impl->getServices(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            // provider offline — page still renders
        }

        $recentLogs = \App\Models\ProviderLog::where('provider_id', $provider->id)
            ->latest()->limit(15)->get();

        return view('admin.providers.edit', compact('provider', 'services', 'countries', 'stats', 'liveBalance', 'catalog', 'recentLogs'));
    }

    public function update(Request $request, Provider $provider)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'base_url' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $old = $provider->only(['name', 'base_url', 'is_active']);

        // Never overwrite the stored key with a blank value
        if (empty($validated['api_key'])) {
            unset($validated['api_key']);
        }

        $validated['is_active'] = $request->boolean('is_active');
        $provider->update($validated);

        $this->auditService->log('provider.update', $provider, $old, $provider->only(['name', 'base_url', 'is_active']));

        return redirect()->route('admin.providers.index')->with('success', 'Provider updated.');
    }

    public function toggle(Provider $provider)
    {
        $old = ['is_active' => $provider->is_active];
        $provider->update(['is_active' => !$provider->is_active]);
        $this->auditService->log('provider.toggle', $provider, $old, ['is_active' => $provider->is_active]);

        return back()->with('success', 'Provider ' . ($provider->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function storeServiceMapping(Request $request, Provider $provider)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'provider_service_code' => 'required|string|max:50',
            'cost' => 'required|numeric|min:0',
        ]);

        $mapping = $provider->providerServices()->updateOrCreate(
            ['service_id' => $validated['service_id']],
            [
                'provider_service_code' => $validated['provider_service_code'],
                'cost' => $validated['cost'],
                'is_active' => true,
            ]
        );

        $this->auditService->log('provider.service_map', $provider, null, $validated);

        return back()->with('success', 'Service mapping saved.');
    }

    public function toggleServiceMapping(Provider $provider, \App\Models\ProviderService $mapping)
    {
        abort_unless($mapping->provider_id === $provider->id, 404);
        $mapping->update(['is_active' => !$mapping->is_active]);
        $this->auditService->log('provider.service_toggle', $provider, null, [
            'service_id' => $mapping->service_id,
            'is_active' => $mapping->is_active,
        ]);

        return back()->with('success', 'Service mapping updated.');
    }

    public function destroyServiceMapping(Provider $provider, \App\Models\ProviderService $mapping)
    {
        abort_unless($mapping->provider_id === $provider->id, 404);
        $mapping->delete();
        $this->auditService->log('provider.service_unmap', $provider, null, ['service_id' => $mapping->service_id]);

        return back()->with('success', 'Service mapping removed.');
    }

    public function storeCountryMapping(Request $request, Provider $provider)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'provider_country_code' => 'required|string|max:50',
        ]);

        $provider->providerCountries()->updateOrCreate(
            ['country_id' => $validated['country_id']],
            [
                'provider_country_code' => $validated['provider_country_code'],
                'is_active' => true,
            ]
        );

        $this->auditService->log('provider.country_map', $provider, null, $validated);

        return back()->with('success', 'Country mapping saved.');
    }

    public function toggleCountryMapping(Provider $provider, \App\Models\ProviderCountry $mapping)
    {
        abort_unless($mapping->provider_id === $provider->id, 404);
        $mapping->update(['is_active' => !$mapping->is_active]);
        $this->auditService->log('provider.country_toggle', $provider, null, [
            'country_id' => $mapping->country_id,
            'is_active' => $mapping->is_active,
        ]);

        return back()->with('success', 'Country mapping updated.');
    }

    public function destroyCountryMapping(Provider $provider, \App\Models\ProviderCountry $mapping)
    {
        abort_unless($mapping->provider_id === $provider->id, 404);
        $mapping->delete();
        $this->auditService->log('provider.country_unmap', $provider, null, ['country_id' => $mapping->country_id]);

        return back()->with('success', 'Country mapping removed.');
    }
}
