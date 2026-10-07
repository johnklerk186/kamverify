<?php

namespace Tests\Feature;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

/**
 * Admin "Re-check provider status" — recovers payments that succeeded
 * at the provider but were never confirmed to us (e.g. missed webhook).
 * In tests Fapshi runs in mock mode, whose status lookup reports
 * 'completed', exercising the real verify → credit path.
 */
class AdminPaymentRecheckTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    protected function pendingPayment($user): Payment
    {
        return Payment::create([
            'payment_id' => 'KV-PAY-STUCK01',
            'user_id' => $user->id,
            'amount' => 1500,
            'currency' => 'XAF',
            'status' => 'pending',
            'provider' => 'fapshi',
            'provider_payment_id' => 'FAPSHI-TXN-STUCK',
        ]);
    }

    public function test_recheck_credits_pending_payment(): void
    {
        $this->seedMarketplace();
        $customer = $this->customer();
        $payment = $this->pendingPayment($customer);
        $before = $customer->wallet->fresh()->balance;

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.payments.recheck', $payment))
            ->assertRedirect();

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertEquals($before + 1500, (float) $customer->wallet->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'deposit',
            'amount' => 1500,
            'user_id' => $customer->id,
        ]);
    }

    public function test_recheck_is_idempotent_no_double_credit(): void
    {
        $this->seedMarketplace();
        $customer = $this->customer();
        $payment = $this->pendingPayment($customer);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post(route('admin.payments.recheck', $payment));
        $this->actingAs($admin, 'admin')->post(route('admin.payments.recheck', $payment));

        $this->assertSame(1, $customer->wallet->fresh()->transactions()
            ->where('type', 'deposit')->where('amount', 1500)->count());
    }

    public function test_recheck_rejects_non_pending_payment(): void
    {
        $this->seedMarketplace();
        $customer = $this->customer();
        $payment = $this->pendingPayment($customer);
        $payment->update(['status' => 'failed']);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.payments.recheck', $payment))
            ->assertRedirect();

        // still failed — a failed payment must never resurrect into a credit
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame(0, $customer->wallet->fresh()->transactions()
            ->where('type', 'deposit')->where('amount', 1500)->count());
    }

    public function test_customers_cannot_recheck(): void
    {
        $this->seedMarketplace();
        $customer = $this->customer();
        $payment = $this->pendingPayment($customer);

        $this->actingAs($customer)->post(route('admin.payments.recheck', $payment));

        $this->assertSame('pending', $payment->fresh()->status);
    }
}
