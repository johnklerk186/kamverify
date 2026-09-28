<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user', 'service', 'country', 'provider']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('service_id')) {
            $query->where('service_id', $request->service_id);
        }
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }

        $orders = $query->latest()->paginate(25)->withQueryString();
        $services = Service::orderBy('name')->get();
        $countries = Country::orderBy('name')->get();
        $statusCounts = Order::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.orders.index', compact('orders', 'services', 'countries', 'statusCounts'));
    }

    public function show(Order $order)
    {
        $order->load(['user', 'service', 'country', 'provider', 'smsMessages']);

        return view('admin.orders.show', compact('order'));
    }

    /**
     * Audited administrative override — deliberately narrow. Admins may
     * only force an order into failed/cancelled/refunded to resolve
     * stuck activations; they can never fabricate progress states like
     * number_assigned or completed. Every override is audit-logged.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:failed,cancelled,refunded',
            'reason' => 'required|string|min:5|max:500',
        ]);

        $oldStatus = $order->status;
        $target = $request->input('status');

        if ($oldStatus === $target) {
            return back()->with('warning', "Order is already {$target}.");
        }

        try {
            if ($target === 'refunded') {
                // refundOrder is idempotent + row-locked — safe to call
                // even if a refund was partially processed before.
                app(\App\Services\OrderService::class)->refundOrder($order);
            } else {
                app(\App\Services\OrderService::class)->updateStatus($order, $target, force: true);
            }

            app(\App\Services\AuditService::class)->log(
                'order.status_override',
                $order,
                ['status' => $oldStatus],
                ['status' => $target, 'reason' => $request->input('reason')]
            );

            return back()->with('success', "Order status overridden: {$oldStatus} → {$target}.");

        } catch (\Exception $e) {
            return back()->with('error', 'Override failed: ' . $e->getMessage());
        }
    }
}
