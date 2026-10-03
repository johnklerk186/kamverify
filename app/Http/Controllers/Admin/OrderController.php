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
            $orderService = app(\App\Services\OrderService::class);

            // An active order may hold a paid provider activation — try to
            // release it first so KamVerify isn't charged for a number the
            // customer was refunded for. If the provider reports a code was
            // received, the order is effectively delivered: refuse.
            $providerOutcome = 'none';
            if ($order->provider_activation_id && $order->isActive()) {
                try {
                    $provider = app(\App\Services\ProviderService::class)
                        ->getProviderForModel($order->provider);
                    $provider->cancelActivation($order->provider_activation_id);
                    $providerOutcome = 'released';
                } catch (\App\Exceptions\HeroSmsException $e) {
                    if (in_array($e->errorCode, ['OTP_RECEIVED', 'NEW_OTP_RECEIVED'], true)) {
                        return back()->with('error',
                            'Provider reports a code was already received — this order is delivered, not refundable.');
                    }
                    // FINISHED/CANCELED/REFUNDED or other refusal — the
                    // provider side is settled one way or the other.
                    $providerOutcome = in_array($e->errorCode, ['FINISHED', 'CANCELED', 'REFUNDED'], true)
                        ? 'provider_resolved' : 'cancel_rejected';
                } catch (\Throwable $e) {
                    $providerOutcome = 'provider_unreachable';
                }
            } elseif (in_array($order->status, ['completed', 'sms_received'], true)) {
                $providerOutcome = 'consumed';
            }

            if ($target === 'refunded') {
                // refundOrder is idempotent + row-locked — safe to call
                // even if a refund was partially processed before.
                $orderService->refundOrder($order, null, 'admin_override', $providerOutcome);
            } else {
                // failed/cancelled — wind the order down AND refund; a
                // cancelled order must never silently keep the money.
                $orderService->updateStatus($order, $target, force: true);
                $orderService->refundOrder($order->fresh(), null, 'admin_override', $providerOutcome);
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
