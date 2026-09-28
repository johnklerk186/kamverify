<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::orderBy('name')->paginate(20);
        
        return view('admin.services.index', compact('services'));
    }

    public function create()
    {
        return view('admin.services.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:services',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'customer_enabled' => 'boolean',
        ]);

        Service::create($request->only([
            'name', 'slug', 'description', 'icon', 'is_active', 'customer_enabled',
        ]));

        return redirect()->route('admin.services.index')
            ->with('success', 'Service created successfully.');
    }

    public function show(Service $service)
    {
        return view('admin.services.show', compact('service'));
    }

    public function edit(Service $service)
    {
        return view('admin.services.edit', compact('service'));
    }

    public function update(Request $request, Service $service)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:services,slug,' . $service->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'customer_enabled' => 'boolean',
        ]);

        $service->update($request->only([
            'name', 'slug', 'description', 'icon', 'is_active', 'customer_enabled',
        ]));

        return redirect()->route('admin.services.index')
            ->with('success', 'Service updated successfully.');
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return redirect()->route('admin.services.index')
            ->with('success', 'Service deleted successfully.');
    }

    public function toggleStatus(Service $service)
    {
        $service->update(['is_active' => !$service->is_active]);

        return back()->with('success', 'Service status updated successfully.');
    }

    /**
     * Storefront visibility — whether customers can buy this service.
     */
    public function toggleCustomer(Service $service)
    {
        $service->update(['customer_enabled' => !$service->customer_enabled]);

        return back()->with('success', 'Customer visibility updated.');
    }
}