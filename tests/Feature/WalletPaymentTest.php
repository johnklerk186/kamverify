<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

class WalletPaymentTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    public function test_deposit_credits_wallet_with_transaction(): void
    {
        $user = $this->customer();
        $wallet = app(WalletService::class);

        $tx = $wallet->deposit($user, 2500, 'Test deposit');

        $this->assertEquals(52500, (float) $user->wallet->fresh()->balance);
        $this->assertNotNull($tx->transaction_id);
        $this->assertDatabaseHas('wallet_transactions', [
            'id' => $tx->id,
            'type' => 'deposit',
        ]);
    }

    public function test_withdrawal_blocked_when_balance_insufficient(): void
    {
        $user = $this->customer();
        $wallet = app(WalletService::class);

        try {
            $wallet->withdraw($user, 999999, 'Overdraw attempt');
            $this->fail('Expected insufficient balance exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('nsufficient', $e->getMessage());
        }

        $this->assertEquals(50000, (float) $user->wallet->fresh()->balance);
    }

    public function test_negative_amounts_are_rejected(): void
    {
        $user = $this->customer();
        $wallet = app(WalletService::class);

        $this->expectException(\Exception::class);
        $wallet->deposit($user, -10);
    }

    public function test_duplicate_payment_processing_credits_once(): void
    {
        $user = $this->customer();
        $payments = app(PaymentService::class);

        $payment = $payments->createPayment($user, 2000, 'mock');
        $balanceBefore = (float) $user->wallet->fresh()->balance;

        // Simulate webhook + callback both arriving (the classic double-hit)
        $payments->processSuccessfulPayment($payment);
        $payments->processSuccessfulPayment($payment->fresh());
        $payments->processSuccessfulPayment($payment->fresh());

        $this->assertEquals($balanceBefore + 2000, (float) $user->wallet->fresh()->balance);
        $this->assertSame('completed', $payment->fresh()->status);
    }

    public function test_wallet_is_never_credited_by_frontend_status_alone(): void
    {
        $user = $this->customer();
        $payments = app(PaymentService::class);

        // A payment is created pending — nobody may mark it completed
        // without going through processSuccessfulPayment.
        $payment = $payments->createPayment($user, 2000, 'mock');
        $this->assertSame('pending', $payment->status);
        $this->assertEquals(50000, (float) $user->wallet->fresh()->balance);
    }

    public function test_every_transaction_has_unique_reference(): void
    {
        $user = $this->customer();
        $wallet = app(WalletService::class);

        $ids = collect(range(1, 5))->map(fn () => $wallet->deposit($user, 100)->transaction_id);

        $this->assertSame(5, $ids->unique()->count());
    }

    public function test_payment_references_are_unique(): void
    {
        $user = $this->customer();
        $payments = app(PaymentService::class);

        $refs = collect(range(1, 3))->map(fn () => $payments->createPayment($user, 500, 'mock')->payment_id);

        $this->assertSame(3, $refs->unique()->count());
    }

    public function test_success_after_terminal_failure_is_blocked(): void
    {
        $user = $this->customer();
        $payments = app(PaymentService::class);

        $payment = $payments->createPayment($user, 2000, 'mock');
        $payments->processFailedPayment($payment, 'test failure');

        // A late "success" must never credit a payment already failed
        $payments->processSuccessfulPayment($payment->fresh());

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertEquals(50000, (float) $user->wallet->fresh()->balance);
    }
}
