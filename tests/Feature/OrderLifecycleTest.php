<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SmsMessage;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    protected function buy($user, $country, $service): Order
    {
        $response = $this->actingAs($user)->post('/orders', [
            'country_id' => $country->id,
            'service_id' => $service->id,
        ]);

        $order = Order::latest('id')->first();
        $response->assertRedirect(route('orders.show', $order->id));

        return $order->fresh();
    }

    /** Backdate the mock activation so the next SMS poll "receives" it. */
    protected function primeSms(Order $order): void
    {
        Cache::put("mock_activation:{$order->provider_activation_id}", now()->timestamp - 100, 3600);
    }

    public function test_purchase_assigns_number_and_waits_for_sms(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();

        $order = $this->buy($user, $country, $service);

        $this->assertSame('waiting_for_sms', $order->status);
        $this->assertNotNull($order->phone_number);
        $this->assertNotNull($order->provider_activation_id);
        $this->assertEquals(50000 - $order->selling_price, (float) $user->wallet->fresh()->balance);
    }

    public function test_sms_arrival_auto_completes_order(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);
        $this->primeSms($order);

        $this->actingAs($user)->get(route('orders.sms-feed', $order->id))
            ->assertOk()
            ->assertJson(['status' => 'completed']);

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertSame(1, $order->smsMessages()->count());
        $this->assertNotNull($order->smsMessages->first()->otp_code);
    }

    public function test_repeated_sms_polls_do_not_duplicate_messages(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);
        $this->primeSms($order);

        $this->actingAs($user)->get(route('orders.sms-feed', $order->id));
        $this->actingAs($user)->post(route('orders.refresh-sms', $order->id));
        $this->actingAs($user)->get(route('orders.sms-feed', $order->id));

        $this->assertSame(1, SmsMessage::where('order_id', $order->id)->count());
    }

    public function test_cancel_before_sms_refunds_wallet(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);
        $price = (float) $order->selling_price;

        $this->actingAs($user)->delete(route('orders.cancel', $order->id))
            ->assertRedirect(route('orders.index'));

        $order->refresh();
        $this->assertSame('refunded', $order->status);
        $this->assertEquals(50000, (float) $user->wallet->fresh()->balance);
        $this->assertEquals($price, (float) $order->refund_amount);
        $this->assertSame(1, \App\Models\Refund::where('order_id', $order->id)->count());
    }

    public function test_early_cancel_is_accepted_and_auto_refunds_via_job(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);
        $price = (float) $order->selling_price;

        // Provider denies cancels for the first 2 calls, then accepts.
        $fake = new class extends \App\Services\Providers\HeroSmsProvider {
            public int $cancelCalls = 0;

            public function cancelActivation(string $activationId): array
            {
                $this->cancelCalls++;
                if ($this->cancelCalls < 3) {
                    throw new \App\Exceptions\HeroSmsException('EARLY_CANCEL_DENIED', 'too early');
                }
                return ['activation_id' => $activationId, 'status' => 'cancelled'];
            }
        };
        // Bind the same ProviderService instance the job will resolve,
        // so the fake provider is shared across controller + job calls.
        $ps = app(\App\Services\ProviderService::class);
        $ps->registerProvider('herosms', $fake);
        app()->instance(\App\Services\ProviderService::class, $ps);

        $this->actingAs($user)->delete(route('orders.cancel', $order->id))
            ->assertRedirect();

        // Sync queue executes the job + its retries inline: the deferred
        // cancellation completes with a refund and the flag cleared.
        $order->refresh();
        $this->assertSame('refunded', $order->status);
        $this->assertNull($order->cancel_requested_at);
        $this->assertEquals(50000, (float) $user->wallet->fresh()->balance);
        $this->assertSame(3, $fake->cancelCalls);
    }

    public function test_deferred_cancel_aborts_when_code_arrives(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);
        $balanceAfterBuy = (float) $user->wallet->fresh()->balance;

        $order->update([
            'status' => 'sms_received',
            'cancel_requested_at' => now(),
        ]);

        (new \App\Jobs\CancelOrderJob($order->id))
            ->handle(app(\App\Services\ProviderService::class), app(OrderService::class));

        $order->refresh();
        $this->assertSame('sms_received', $order->status);
        $this->assertNull($order->cancel_requested_at);
        $this->assertEquals($balanceAfterBuy, (float) $user->wallet->fresh()->balance);
        $this->assertSame(0, \App\Models\Refund::where('order_id', $order->id)->count());
    }

    public function test_refund_is_idempotent(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        $service2 = app(OrderService::class);
        $service2->cancelOrder($order);
        $service2->refundOrder($order->fresh());
        $service2->refundOrder($order->fresh());

        $this->assertSame(1, \App\Models\Refund::where('order_id', $order->id)->count());
        $this->assertEquals(50000, (float) $user->wallet->fresh()->balance);
    }

    public function test_completed_order_cannot_be_cancelled(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);
        $this->primeSms($order);
        $this->actingAs($user)->get(route('orders.sms-feed', $order->id));

        $this->assertSame('completed', $order->fresh()->status);

        $response = $this->actingAs($user)->delete(route('orders.cancel', $order->id));
        // policy denies or controller blocks — either way no refund, no cancel
        $this->assertContains($response->status(), [302, 403]);
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_expired_order_auto_refunds(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        $order->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($user)->get(route('orders.sms-feed', $order->id));

        $order->refresh();
        $this->assertSame('expired', $order->status);
        $this->assertEquals(50000, (float) $user->wallet->fresh()->balance);
    }

    public function test_illegal_transitions_are_rejected(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);
        $this->primeSms($order);
        $this->actingAs($user)->get(route('orders.sms-feed', $order->id));

        $os = app(OrderService::class);
        $this->expectException(\Exception::class);
        $os->updateStatus($order->fresh(), 'pending');
    }

    public function test_insufficient_balance_blocks_purchase(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $user->wallet->update(['balance' => 1]);

        $before = Order::count();
        $response = $this->actingAs($user)->post('/orders', [
            'country_id' => $country->id,
            'service_id' => $service->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame($before, Order::count());
    }

    public function test_customer_cannot_view_other_users_order(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $owner = $this->customer();
        $intruder = $this->customer();
        $order = $this->buy($owner, $country, $service);

        $this->actingAs($intruder)->get(route('orders.show', $order->id))->assertForbidden();
        $this->actingAs($intruder)->delete(route('orders.cancel', $order->id))->assertForbidden();
    }
}
