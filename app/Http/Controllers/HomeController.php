<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        // Capture referral links shared by existing customers
        if ($request->filled('ref')) {
            session(['referral_code' => strtoupper(trim($request->query('ref')))]);
        }

        $services = Service::customerEnabled()->orderBy('sort_order')->orderBy('name')->limit(12)->get();
        $countries = Country::active()->orderBy('sort_order')->orderBy('name')->limit(15)->get();

        // Real platform statistics only — no invented numbers
        $stats = [
            'countries' => Country::active()->count(),
            'services' => Service::customerEnabled()->count(),
            'orders_completed' => Order::whereIn('status', ['completed', 'sms_received'])->count(),
            'customers' => User::where('role', 'customer')->count(),
        ];

        return view('welcome', compact('services', 'countries', 'stats'));
    }
}
