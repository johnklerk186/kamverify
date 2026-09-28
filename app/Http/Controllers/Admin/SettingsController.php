<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group');
        
        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);

        foreach ($request->settings as $settingData) {
            Setting::where('key', $settingData['key'])->update([
                'value' => $settingData['value'],
            ]);
        }

        return back()->with('success', 'Settings updated successfully.');
    }

    public function create(Request $request)
    {
        $request->validate([
            'key' => 'required|string|unique:settings',
            'value' => 'required',
            'type' => 'required|in:string,integer,float,boolean,json',
            'group' => 'required|string',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        Setting::create($request->only(['key', 'value', 'type', 'group', 'description', 'is_public']));

        return back()->with('success', 'Setting created successfully.');
    }

    public function destroy(Setting $setting)
    {
        $setting->delete();

        return back()->with('success', 'Setting deleted successfully.');
    }
}