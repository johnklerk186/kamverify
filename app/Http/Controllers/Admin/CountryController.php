<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function index()
    {
        $countries = Country::orderBy('name')->paginate(20);
        
        return view('admin.countries.index', compact('countries'));
    }

    public function create()
    {
        return view('admin.countries.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:countries',
            'dial_code' => 'required|string|max:10',
            'flag' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        Country::create($request->all());

        return redirect()->route('admin.countries.index')
            ->with('success', 'Country created successfully.');
    }

    public function show(Country $country)
    {
        return view('admin.countries.show', compact('country'));
    }

    public function edit(Country $country)
    {
        return view('admin.countries.edit', compact('country'));
    }

    public function update(Request $request, Country $country)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:countries,code,' . $country->id,
            'dial_code' => 'required|string|max:10',
            'flag' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $country->update($request->all());

        return redirect()->route('admin.countries.index')
            ->with('success', 'Country updated successfully.');
    }

    public function destroy(Country $country)
    {
        $country->delete();

        return redirect()->route('admin.countries.index')
            ->with('success', 'Country deleted successfully.');
    }

    public function toggleStatus(Country $country)
    {
        $country->update(['is_active' => !$country->is_active]);

        return back()->with('success', 'Country status updated successfully.');
    }

    /**
     * Popular is a static badge/chip attribute only — it never affects
     * availability, pricing or provider mappings.
     */
    public function togglePopular(Country $country)
    {
        $country->update(['is_popular' => !$country->is_popular]);

        // Bust cached storefront lists for every customer-facing service
        $availability = app(\App\Services\CountryAvailabilityService::class);
        \App\Models\Service::where('customer_enabled', true)->each(
            fn ($s) => $availability->forget($s));

        return back()->with('success',
            "{$country->name} " . ($country->is_popular ? 'marked popular.' : 'removed from popular.'));
    }
}