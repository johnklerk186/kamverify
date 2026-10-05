<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        // Capture referral links shared by existing customers
        if ($request->filled('ref')) {
            session(['referral_code' => strtoupper(trim($request->query('ref')))]);
        }

        // customerVisible keeps outage-flagged services listed with a
        // "Temporarily Unavailable" badge; only purchase gates exclude them.
        $services = Service::customerVisible()->orderBy('sort_order')->orderBy('name')->limit(12)->get();
        $countries = Country::active()->orderBy('sort_order')->orderBy('name')->limit(15)->get();

        // Avg SMS delivery measured from real orders: order placed →
        // code received (HeroSMS numbers). Computed in PHP so it works
        // on sqlite/MySQL alike.
        $avgSecs = Order::whereIn('status', ['completed', 'sms_received'])
            ->whereNotNull('completed_at')
            ->latest('id')->limit(500)
            ->get(['created_at', 'completed_at'])
            ->avg(fn ($o) => max(0, $o->completed_at->diffInSeconds($o->created_at)));
        $stats = [
            'countries' => Country::active()->count(),
            'services' => 520, // full HeroSMS-supported service catalog
            'orders_completed' => Order::whereIn('status', ['completed', 'sms_received'])->count(),
            'avg_delivery' => $avgSecs === null ? '~15s'
                : ($avgSecs < 90 ? round($avgSecs) . 's' : round($avgSecs / 60, 1) . ' min'),
        ];

        return view('welcome', compact('services', 'countries', 'stats'));
    }
}
