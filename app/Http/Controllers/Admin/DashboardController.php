<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Payment;
use App\Models\Country;
use App\Models\Service;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(AnalyticsService $analytics)
    {
        $stats = $analytics->dashboardOverview();

        // Recent orders
        $recentOrders = Order::with(['user', 'service', 'country'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Recent transactions
        $recentTransactions = WalletTransaction::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Recent users
        $recentUsers = User::where('role', 'customer')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.dashboard.index', compact(
            'stats',
            'recentOrders',
            'recentTransactions',
            'recentUsers'
        ));
    }
}