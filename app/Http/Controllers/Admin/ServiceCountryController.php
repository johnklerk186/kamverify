<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Service;
use App\Models\ServiceCountry;
use App\Services\AuditService;
use App\Services\CountryAvailabilityService;
use Illuminate\Http\Request;

/**
 * Per-service country management: which countries are sellable for
 * each storefront service, and which appear in the "popular" chips.
 */
class ServiceCountryController extends Controller
{
    public function __construct(
        protected CountryAvailabilityService $availability,
        protected AuditService $auditService
    ) {}

    public function index(Request $request)
    {
        $services = Service::customerEnabled()->orderBy('name')->get();
        $service = $services->firstWhere('id', (int) $request->query('service_id'))
            ?? $services->first();

        $data = $service ? $this->availability->forServiceAdmin($service) : ['count' => 0, 'popular' => [], 'countries' => []];

        return view('admin.service-countries.index', [
            'services' => $services,
            'service' => $service,
            'countries' => $data['countries'],
            'popularIds' => $data['popular'],
            'count' => $data['count'],
        ]);
    }

    public function update(Request $request, Service $service, Country $country)
    {
        $validated = $request->validate([
            'action' => 'required|in:toggle_popular,toggle_enabled,move_up,move_down',
        ]);

        $row = ServiceCountry::firstOrCreate(
            ['service_id' => $service->id, 'country_id' => $country->id],
            ['is_enabled' => true, 'is_popular' => false, 'popular_sort' => 0]
        );

        $old = $row->only('is_enabled', 'is_popular', 'popular_sort');
        $action = $validated['action'];

        if ($action === 'toggle_enabled') {
            $row->is_enabled = !$row->is_enabled;
            if (!$row->is_enabled) {
                $row->is_popular = false; // a disabled country can't be popular
            }
        } elseif ($action === 'toggle_popular') {
            if (!$row->is_enabled) {
                return back()->with('error', "{$country->name} is disabled for {$service->name} — enable it first.");
            }
            $row->is_popular = !$row->is_popular;
            $row->popular_sort = $row->is_popular
                ? ((int) ServiceCountry::where('service_id', $service->id)->max('popular_sort')) + 1
                : 0;
        } else {
            // Reorder within the popular list for this service
            $populars = ServiceCountry::where('service_id', $service->id)
                ->where('is_popular', true)->orderBy('popular_sort')->get();
            $ids = $populars->pluck('country_id')->all();
            $pos = array_search($country->id, $ids, true);
            if ($pos === false) {
                return back()->with('error', "{$country->name} is not marked popular for {$service->name}.");
            }
            $swap = $action === 'move_up' ? $pos - 1 : $pos + 1;
            if (isset($ids[$swap])) {
                [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
                foreach ($ids as $i => $id) {
                    ServiceCountry::where('service_id', $service->id)
                        ->where('country_id', $id)->update(['popular_sort' => $i + 1]);
                }
                $row->refresh();
            }
        }

        $row->save();
        $this->availability->forget($service);

        $this->auditService->log('pricing.country_service.update', $service, $old,
            $row->only('is_enabled', 'is_popular', 'popular_sort') + ['country' => $country->code]);

        return back()->with('success', "{$service->name} / {$country->name} updated.");
    }
}
