<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use App\Services\WalletService;
use App\Services\ProviderService;
use App\Services\PricingService;
use App\Services\SmsService;
use App\Models\Country;
use App\Models\Service;
use App\Models\Order;
use App\Models\Provider;
use App\Http\Requests\StoreOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected OrderService $orderService;
    protected WalletService $walletService;
    protected ProviderService $providerService;
    protected PricingService $pricingService;
    protected SmsService $smsService;

    public function __construct(
        OrderService $orderService,
        WalletService $walletService,
        ProviderService $providerService,
        PricingService $pricingService,
        SmsService $smsService
    ) {
        $this->orderService = $orderService;
        $this->walletService = $walletService;
        $this->providerService = $providerService;
        $this->pricingService = $pricingService;
        $this->smsService = $smsService;
    }

    public function create()
    {
        $countries = Country::active()->orderBy('name')->get();
        // customerVisible keeps temporarily-unavailable services listed —
        // they render with an outage badge and cannot be purchased.
        $services = Service::customerVisible()->orderBy('name')->get();
        $wallet = $this->walletService->getWallet(Auth::user());
        $serviceSlugs = $services->pluck('slug', 'id');
        $unavailableIds = $services->where('temporarily_unavailable', true)->pluck('id')->values();

        return view('orders.create', compact('countries', 'services', 'wallet', 'serviceSlugs', 'unavailableIds'));
    }

    /**
     * Per-service country list for the buy flow — JSON feed consumed
     * after the customer picks a service. Only countries that are
     * mapped, enabled, and (when the provider answers) in stock.
     */
    public function serviceCountries(Request $request)
    {
        $request->validate(['service_id' => 'required|exists:services,id']);

        $service = Service::where('id', $request->service_id)->customerEnabled()->first();
        if (!$service) {
            return response()->json(['message' => $this->serviceDisabledMessage($request->service_id)], 422);
        }

        $data = app(\App\Services\CountryAvailabilityService::class)->forService($service);

        return response()->json([
            'service'   => ['id' => $service->id, 'name' => $service->name, 'slug' => $service->slug],
            'count'     => $data['count'],
            'popular'   => $data['popular'],
            'countries' => $data['countries'],
        ]);
    }

    /**
     * Real-time price + availability quote for the buy-number flow.
     * Returns JSON consumed by the purchase widget.
     */
    public function quote(Request $request)
    {
        $request->validate([
            'country_id' => 'required|exists:countries,id',
            'service_id' => 'required|exists:services,id',
        ]);

        $country = Country::where('id', $request->country_id)->where('is_active', true)->first();
        $service = Service::where('id', $request->service_id)->customerEnabled()->first();

        if (!$country || !$service) {
            return response()->json([
                'available' => false,
                'message' => $this->serviceDisabledMessage($request->service_id),
            ], 422);
        }

        try {
            // Route per-service: facebook → TextVerified, others → HeroSMS.
            $providerModel = $this->providerService->providerFor($service, $country);
            if (!$providerModel) {
                return response()->json(['available' => false, 'message' => 'No provider serves this combination right now.'], 422);
            }
            $provider = $this->providerService->getProviderForModel($providerModel);

            $numbers = $provider->getAvailableNumbers($country->code, $service->slug);
            $available = !empty($numbers);
            $providerCost = $available ? (float) $numbers[0]['cost'] : null;
            $price = $available ? $this->pricingService->calculateSellingPrice($providerCost, $country, $service) : null;
            $balance = $this->walletService->getBalance(Auth::user());

            return response()->json([
                'available' => $available,
                'price' => $price,
                'price_formatted' => $price !== null ? xaf($price) : null,
                'balance' => (int) round($balance),
                'balance_formatted' => xaf($balance),
                'sufficient' => $available ? $balance >= $price : false,
                'message' => $available
                    ? 'Numbers are available for this combination.'
                    : 'No numbers available for this combination right now.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'available' => false,
                'message' => 'Unable to check availability right now. Please try again.',
            ], 503);
        }
    }

    /**
     * Customer-facing reason a service id isn't buyable — the outage
     * copy for temporarily-unavailable services, generic otherwise.
     */
    protected function serviceDisabledMessage($serviceId): string
    {
        $service = Service::find($serviceId);

        return $service && $service->temporarily_unavailable
            ? $service->outageMessage()
            : 'This service is currently disabled.';
    }

    /**
     * Lightweight JSON feed polled by the active-order screen.
     */
    public function smsFeed(Order $order)
    {
        $this->authorize('view', $order);

        // Auto-expire orders whose window has passed. Orders that hold a
        // provider activation MUST go through ExpireOrderJob — it releases
        // the activation at HeroSMS (or ingests a late OTP) before the
        // local refund. Calling expireOrder directly here would refund the
        // customer while the provider still holds the activation cost.
        if ($order->isExpired()) {
            try {
                if (in_array($order->status, ['number_assigned', 'waiting_for_sms'])) {
                    \App\Jobs\ExpireOrderJob::dispatchSync($order);
                } elseif (in_array($order->status, ['pending', 'processing'])) {
                    // No provider activation purchased — nothing to release.
                    $this->orderService->expireOrder($order, 'expired_no_sms', 'none');
                }
                $order->refresh();
            } catch (\Exception $e) {
                // already handled by background job; ignore
            }
        }

        // Pull fresh SMS from provider for live orders — throttled per
        // activation (5s) so concurrent viewers don't multiply provider
        // calls against the account's RPS limit.
        if (in_array($order->status, ['number_assigned', 'waiting_for_sms'])
            && \Illuminate\Support\Facades\Cache::add('sms-poll:' . $order->provider_activation_id, 1, 5)) {
            try {
                $provider = $this->providerService->getProviderForModel($order->provider);
                $smsData = $provider->getSms($order->provider_activation_id);
                foreach ($smsData as $sms) {
                    $this->smsService->receiveSms(
                        $order,
                        $sms['sender'],
                        $sms['message'],
                        $sms['id'] ?? null
                    );
                }
                $order->refresh();
            } catch (\Exception $e) {
                // provider hiccup — return what we have
            }
        }

        $messages = $this->smsService->getOrderSmsMessages($order)->map(fn ($sms) => [
            'sender' => $sms->sender,
            'message' => $sms->message,
            'otp_code' => $sms->otp_code,
            'received_at' => $sms->received_at->format('M d, Y H:i:s'),
        ]);

        return response()->json([
            'status' => $order->status,
            'sms_count' => $messages->count(),
            'messages' => $messages,
            'expires_at' => $order->expires_at?->toIso8601String(),
            'is_active' => $order->isActive(),
        ]);
    }

    public function store(StoreOrderRequest $request)
    {
        $user = Auth::user();

        // One purchase per user at a time — kills double-click/double-submit
        // duplicates regardless of what the frontend does.
        $lock = \Illuminate\Support\Facades\Cache::lock('order-purchase:' . $user->id, 20);

        if (!$lock->get()) {
            return back()->with('error', 'A purchase is already in progress. Please wait a moment.');
        }

        try {
            $country = Country::where('id', $request->country_id)->where('is_active', true)->firstOrFail();
            $service = Service::where('id', $request->service_id)->customerEnabled()->first();
            if (!$service) {
                return back()->with('error', $this->serviceDisabledMessage($request->service_id));
            }
            // Route per-service: facebook → TextVerified, others → HeroSMS.
            $providerModel = $this->providerService->providerFor($service, $country);
            if (!$providerModel) {
                return back()->with('error', 'This service is temporarily unavailable for the selected country.');
            }
            $provider = $this->providerService->getProviderForModel($providerModel);

            // Get available numbers from provider
            $availableNumbers = $provider->getAvailableNumbers($country->code, $service->slug);

            if (empty($availableNumbers)) {
                return back()->with('error', 'No numbers available for this country and service combination.');
            }

            $providerCost = (float) $availableNumbers[0]['cost'];

            // Create order — balance verified + debited under a wallet row lock
            $order = $this->orderService->createOrder($user, $country, $service, $providerModel, $providerCost);

            try {
                // Purchase number from provider. user_id is forwarded as
                // the reseller buyer ID (see HERO_SMS_RESELLER_* in .env);
                // the provider applies per-buyer bans instead of
                // account-wide ones once reseller status is granted.
                $purchaseResult = $provider->purchaseNumber($country->code, $service->slug, [
                    'user_id' => $user->id,
                    'max_price' => $providerCost,
                ]);

                $this->orderService->assignNumber(
                    $order,
                    $purchaseResult['phone_number'],
                    $purchaseResult['activation_id'],
                    $purchaseResult['cost'] ?? null
                );

                // Tell the provider we're ready to receive the SMS.
                // Non-fatal if it fails — polling still works.
                try {
                    if (method_exists($provider, 'markReady')) {
                        $provider->markReady($purchaseResult['activation_id']);
                    }
                } catch (\Throwable $e) {
                    Log::warning('markReady failed', ['order_id' => $order->order_id]);
                }

                $this->orderService->updateStatus($order, 'waiting_for_sms');
            } catch (\Exception $e) {
                // Provider purchase failed — wind the order back and refund
                // so funds are never stuck on a dead activation.
                $this->orderService->cancelOrder($order, true, true, 'provider_purchase_failed', 'none');

                $user->notify(new \App\Notifications\KamVerifyNotification(
                    'order_failed',
                    'Order Failed',
                    'Your ' . $service->name . ' number could not be purchased — ' . xaf($order->selling_price) . ' was refunded to your wallet.',
                    route('orders.index'),
                    'View Orders',
                    'fa-circle-xmark'
                ));

                return back()->with('error', 'Number purchase failed at the provider. You have not been charged.');
            }

            return redirect()->route('orders.show', $order->id)
                ->with('success', 'Number assigned — use it to request your verification code.');

        } catch (\Exception $e) {
            $message = $e->getMessage() === 'Insufficient wallet balance' || $e->getMessage() === 'Insufficient balance'
                ? 'Insufficient balance. Please deposit funds first.'
                : 'Failed to purchase number: ' . $e->getMessage();

            return back()->with('error', $message);
        } finally {
            $lock->release();
        }
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $filters = [
            'status' => $request->get('status'),
            'service_id' => $request->get('service_id'),
            'country_id' => $request->get('country_id'),
            'search' => $request->get('search'),
        ];

        $orders = $this->orderService->getUserOrders($user, $filters);
        $countries = Country::active()->orderBy('name')->get();
        $services = Service::customerEnabled()->orderBy('name')->get();
        $counts = [
            'all' => Order::where('user_id', $user->id)->count(),
            'active' => Order::where('user_id', $user->id)->active()->count(),
            'completed' => Order::where('user_id', $user->id)->where('status', 'completed')->count(),
            'cancelled' => Order::where('user_id', $user->id)->whereIn('status', ['cancelled', 'refunded'])->count(),
            'expired' => Order::where('user_id', $user->id)->where('status', 'expired')->count(),
        ];

        return view('orders.index', compact('orders', 'countries', 'services', 'filters', 'counts'));
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $smsMessages = $this->smsService->getOrderSmsMessages($order);

        return view('orders.show', compact('order', 'smsMessages'));
    }

    public function refreshSms(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        try {
            $provider = $this->providerService->getProviderForModel($order->provider);
            $smsData = $provider->getSms($order->provider_activation_id);

            $newCount = 0;
            foreach ($smsData as $sms) {
                if ($this->smsService->receiveSms(
                    $order,
                    $sms['sender'],
                    $sms['message'],
                    $sms['id'] ?? null
                )) {
                    $newCount++;
                }
            }
            $order->refresh();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'status' => $order->status,
                    'sms_count' => $order->smsMessages()->count(),
                ]);
            }

            return back()->with('success', 'SMS messages refreshed successfully.');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Refresh failed.'], 500);
            }

            return back()->with('error', 'Failed to refresh SMS: ' . $e->getMessage());
        }
    }

    /**
     * Cancel while the order is still waiting for its SMS — and only if
     * the provider releases the activation. Once an SMS has arrived (or
     * the order completed/expired), cancellation is off the table.
     */
    public function cancel(Order $order)
    {
        $this->authorize('cancel', $order);

        if (!$order->canCancel()) {
            return back()->with('error', 'This order can no longer be cancelled. Cancellation is only possible while waiting for the SMS.');
        }

        // The provider must release the activation before we refund —
        // otherwise we'd refund money the provider still charges us for.
        try {
            $provider = $this->providerService->getProviderForModel($order->provider);
            $provider->cancelActivation($order->provider_activation_id);
        } catch (\App\Exceptions\HeroSmsException $e) {
            if ($e->errorCode === 'EARLY_CANCEL_DENIED') {
                // Provider enforces a ~2 min no-cancel window after purchase.
                // Accept the customer's cancellation now and let the queued
                // job execute it the moment the provider allows it — unless
                // a code arrives first, which aborts the cancellation.
                $order->update(['cancel_requested_at' => now()]);
                \App\Jobs\CancelOrderJob::dispatch($order->id)
                    ->delay(now()->addSeconds(60));

                return back()->with('success',
                    'Cancellation accepted — your refund will be processed automatically within a few minutes unless a code arrives first.');
            }

            if (in_array($e->errorCode, ['FINISHED', 'CANCELED', 'REFUNDED'], true)) {
                // Provider already ended the activation — the cost is not
                // held anymore, so resolving locally with a refund is safe.
                $providerOutcome = 'provider_resolved';
            } else {
                $message = match ($e->errorCode) {
                    'OTP_RECEIVED', 'NEW_OTP_RECEIVED' => 'This number already received a code — the order can no longer be cancelled.',
                    default => 'The provider declined cancellation for this number. Please contact support if you need help.',
                };
                return back()->with('error', $message);
            }
        } catch (\Exception $e) {
            return back()->with('error', 'The provider declined cancellation for this number. Please contact support if you need help.');
        }

        try {
            $this->orderService->cancelOrder($order, true, false, 'customer_cancel', $providerOutcome ?? 'released');

            return redirect()->route('orders.index')
                ->with('success', 'Order cancelled and refunded to your wallet.');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to cancel order: ' . $e->getMessage());
        }
    }
}
