<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Refund;
use App\Models\WalletTransaction;
use App\Services\AnalyticsService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

/**
 * Accounting invariants: "Spent" counts completed orders only,
 * refunds are REFUND transactions (never deposits), purchases are
 * PURCHASE debits, provider cost accounting reflects what HeroSMS
 * actually consumed, and every refund path is idempotent.
 */
class WalletAccountingTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    protected function buy($user, $country, $service): Order
    {
        $this->actingAs($user)->post('/orders', [
            'country_id' => $country->id,
            'service_id' => $service->id,
        ]);

        return Order::latest('id')->first()->fresh();
    }

    protected function complete(Order $order, $user): void
    {
        Cache::put("mock_activation:{$order->provider_activation_id}", now()->timestamp - 100, 3600);
        $this->actingAs($user)->get(route('orders.sms-feed', $order->id));
    }

    /* TEST 1 — completed order counts as spent */
    public function test_completed_order_counts_as_spent(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);
        $this->complete($order, $user);

        $stats = app(OrderService::class)->getOrderStats($user->fresh());
        $this->assertEquals((float) $order->selling_price, $stats['total_spent']);
    }

    /* TEST 2 — cancelled order is NOT spent */
    public function test_cancelled_order_not_counted_as_spent(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        $this->actingAs($user)->delete(route('orders.cancel', $order->id));

        $stats = app(OrderService::class)->getOrderStats($user->fresh());
        $this->assertSame(0.0, $stats['total_spent']);
    }

    /* TEST 3 — refunded/expired orders are NOT spent */
    public function test_refunded_and_expired_orders_not_counted_as_spent(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        $order->update(['expires_at' => now()->subMinute()]);
        app(OrderService::class)->expireOrder($order->fresh());

        $stats = app(OrderService::class)->getOrderStats($user->fresh());
        $this->assertSame(0.0, $stats['total_spent']);
    }

    /* TEST 4 — successful payment credit is a DEPOSIT */
    public function test_successful_payment_credit_is_typed_deposit(): void
    {
        $user = $this->customer();
        $payments = app(PaymentService::class);
        $payment = $payments->createPayment($user, 5000, 'mock');
        $payments->processSuccessfulPayment($payment);

        $txn = WalletTransaction::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame('deposit', $txn->type);
        $this->assertSame(5000.0, (float) $txn->amount);
        $this->assertSame($payment->payment_id, $txn->reference);
    }

    /* TEST 5 — order refund is a REFUND, never a deposit */
    public function test_order_refund_is_typed_refund_not_deposit(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        $this->actingAs($user)->delete(route('orders.cancel', $order->id));

        $refundTxn = WalletTransaction::where('user_id', $user->id)
            ->where('amount', '>', 0)->latest('id')->first();
        $this->assertSame('refund', $refundTxn->type);
        $this->assertSame($order->order_id, $refundTxn->reference);
        $this->assertSame(0, WalletTransaction::where('user_id', $user->id)
            ->where('type', 'deposit')->where('description', 'Order refund')->count());

        // refund must not inflate the deposited total
        $this->assertEquals(50000, (float) $user->wallet->fresh()->total_deposited);
    }

    /* TEST 6 — purchase creates a PURCHASE debit linked to the order */
    public function test_purchase_creates_typed_purchase_debit(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        $debit = WalletTransaction::where('user_id', $user->id)->where('amount', '<', 0)->first();
        $this->assertSame('purchase', $debit->type);
        $this->assertEquals(-(float) $order->selling_price, (float) $debit->amount);
        $this->assertSame($order->order_id, $debit->reference);
    }

    /* TEST 7 + 8 — refund runs once, wallet credited once */
    public function test_refund_cannot_be_processed_twice(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        $svc = app(OrderService::class);
        $svc->cancelOrder($order);
        $svc->refundOrder($order->fresh());
        $svc->refundOrder($order->fresh());
        $svc->refundOrder($order->fresh());

        $this->assertSame(1, Refund::where('order_id', $order->id)->count());
        $this->assertSame(1, WalletTransaction::where('user_id', $user->id)
            ->where('type', 'refund')->count());
        $this->assertEquals(50000, (float) $user->wallet->fresh()->balance);
    }

    /* TEST 9 — cancelled order never lands in completed spending */
    public function test_cancelled_then_spent_stat_stays_zero(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        app(OrderService::class)->cancelOrder($order);

        $this->assertSame(0.0, app(OrderService::class)->totalSpent($user->fresh()));
        // and the wallet was made whole
        $this->assertEquals(50000, (float) $user->wallet->fresh()->balance);
    }

    /* TEST 10 — SMS received = delivered; no cancel, no refund */
    public function test_order_with_sms_cannot_be_cancelled_or_refunded(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);
        $this->complete($order, $user);

        $res = $this->actingAs($user)->delete(route('orders.cancel', $order->id));
        $this->assertContains($res->status(), [302, 403]);

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame(0, Refund::where('order_id', $order->id)->count());
        $this->assertSame(0, WalletTransaction::where('user_id', $user->id)
            ->where('type', 'refund')->count());
    }

    /* TEST 11 — provider-resolved cancellation refunds & records outcome */
    public function test_provider_resolved_cancel_records_outcome_and_refunds(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        $fake = new class extends \App\Services\Providers\HeroSmsProvider {
            public function cancelActivation(string $activationId): array
            {
                throw new \App\Exceptions\HeroSmsException('CANCELED', 'already cancelled');
            }
        };
        $ps = app(\App\Services\ProviderService::class);
        $ps->registerProvider('herosms', $fake);
        app()->instance(\App\Services\ProviderService::class, $ps);

        $this->actingAs($user)->delete(route('orders.cancel', $order->id))
            ->assertRedirect(route('orders.index'));

        $order->refresh();
        $this->assertSame('refunded', $order->status);
        $this->assertSame('provider_resolved', $order->provider_refund_status);
        $this->assertSame('customer_cancel', $order->cancellation_reason);
        $this->assertEquals(50000, (float) $user->wallet->fresh()->balance);
    }

    /* TEST 12 — provider cost / profit truth after cancellation */
    public function test_profit_is_zeroed_when_provider_released_and_loss_when_consumed(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        // Released by provider → money returned → profit 0, cost not lost
        app(OrderService::class)->cancelOrder($order, true, false, 'customer_cancel', 'released');
        $order->refresh();
        $this->assertEquals(0.0, (float) $order->profit);
        $this->assertSame('released', $order->provider_refund_status);

        // Consumed by provider (e.g. admin refund of a delivered order)
        $order2 = $this->buy($user, $country, $service);
        $this->complete($order2, $user);
        app(OrderService::class)->refundOrder($order2->fresh(), null, 'admin_override', 'consumed');
        $order2->refresh();
        $this->assertEquals(-(float) $order2->purchase_price, (float) $order2->profit);
        $this->assertSame('consumed', $order2->provider_refund_status);
    }

    /* TEST 13 — automatic expiry follows the same accounting rules */
    public function test_expiry_refund_is_typed_and_records_provider_outcome(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);
        $order->update(['expires_at' => now()->subMinute()]);

        // The customer page poll must release the provider activation
        // (via ExpireOrderJob) — not refund blindly.
        $this->actingAs($user)->get(route('orders.sms-feed', $order->id));

        $order->refresh();
        $this->assertSame('expired', $order->status);
        $this->assertSame('released', $order->provider_refund_status);
        $this->assertSame('expired_no_sms', $order->cancellation_reason);
        $this->assertSame(1, Refund::where('order_id', $order->id)->count());
        $this->assertSame(1, WalletTransaction::where('user_id', $user->id)
            ->where('type', 'refund')->count());
        $this->assertEquals(50000, (float) $user->wallet->fresh()->balance);
    }

    /* TEST 14 — admin analytics separates deposits from refunds */
    public function test_analytics_separates_deposits_refunds_and_costs(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();

        // Real external deposit
        $payments = app(PaymentService::class);
        $payments->processSuccessfulPayment($payments->createPayment($user, 10000, 'mock'));

        // One completed order, one cancelled order
        $orderDone = $this->buy($user, $country, $service);
        $this->complete($orderDone, $user);
        $orderDead = $this->buy($user, $country, $service);
        app(OrderService::class)->cancelOrder($orderDead);

        $overview = app(AnalyticsService::class)->dashboardOverview();

        $this->assertEquals(10000.0, $overview['total_deposits']);
        $this->assertEquals((float) $orderDead->selling_price, $overview['total_refunds']);
        $this->assertEquals((float) $orderDone->selling_price, $overview['total_sales']);
        $this->assertSame(1, $overview['completed_orders']);
        // cancelled + refunded bucket counts the wound-down order
        $this->assertSame(1, $overview['cancelled_orders']);
    }

    /* TEST 15 — expiry poll on an order whose provider is unreachable
       still resolves locally but records the uncertain outcome */
    public function test_expiry_with_provider_unreachable_records_outcome(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);
        $order->update(['expires_at' => now()->subMinute()]);

        $fake = new class extends \App\Services\Providers\HeroSmsProvider {
            public function cancelActivation(string $activationId): array
            {
                throw new \RuntimeException('connection timeout');
            }
        };
        $ps = app(\App\Services\ProviderService::class);
        $ps->registerProvider('herosms', $fake);
        app()->instance(\App\Services\ProviderService::class, $ps);

        $this->actingAs($user)->get(route('orders.sms-feed', $order->id));

        $order->refresh();
        $this->assertSame('expired', $order->status);
        $this->assertSame('provider_unreachable', $order->provider_refund_status);
        $this->assertEquals(50000, (float) $user->wallet->fresh()->balance);
    }
}
