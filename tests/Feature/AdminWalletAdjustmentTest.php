<?php

namespace Tests\Feature;

use App\Notifications\KamVerifyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

class AdminWalletAdjustmentTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    public function test_admin_can_credit_customer_wallet(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $wallet = $customer->wallet; // starts at 50,000

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.users.wallet-adjust', $customer), [
                'direction' => 'credit',
                'amount' => 2000,
                'reason' => 'manual top-up',
            ]);

        $response->assertSessionHas('success');
        $this->assertEquals(52000, $wallet->fresh()->balance);
        $this->assertEquals(52000, $wallet->fresh()->total_deposited);

        $txn = $wallet->transactions()->latest()->first();
        $this->assertSame('deposit', $txn->type);
        $this->assertEquals(2000, $txn->amount);
        $this->assertStringContainsString('manual top-up', $txn->description);
        $this->assertSame($admin->id, $txn->metadata['admin_id']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.wallet.adjust',
            'user_id' => $admin->id,
            'model_id' => $customer->id,
        ]);

        Notification::assertSentTo($customer, KamVerifyNotification::class);
    }

    public function test_admin_can_debit_customer_wallet(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $wallet = $customer->wallet;

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.users.wallet-adjust', $customer), [
                'direction' => 'debit',
                'amount' => 200,
                'reason' => 'correction',
            ]);

        $response->assertSessionHas('success');
        $this->assertEquals(49800, $wallet->fresh()->balance);
        $this->assertSame('withdrawal', $wallet->transactions()->latest()->first()->type);
    }

    public function test_debit_cannot_exceed_balance(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $wallet = $customer->wallet;

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.users.wallet-adjust', $customer), [
                'direction' => 'debit',
                'amount' => 60000,
                'reason' => 'correction',
            ]);

        $response->assertSessionHas('error');
        $this->assertEquals(50000, $wallet->fresh()->balance);
        $this->assertCount(0, $wallet->transactions);
    }

    public function test_reason_and_amount_are_required(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.users.wallet-adjust', $customer), [
                'direction' => 'credit',
                'amount' => 0,
            ])
            ->assertSessionHasErrors(['amount', 'reason']);
    }

    public function test_customer_cannot_adjust_wallets(): void
    {
        $customer = $this->customer();
        $other = $this->customer();

        $response = $this->actingAs($customer)
            ->post(route('admin.users.wallet-adjust', $other), [
                'direction' => 'credit',
                'amount' => 5000,
                'reason' => 'hack',
            ]);

        // Never reaches the controller — admin middleware redirects/403s
        $this->assertNotSame(200, $response->status());
        $this->assertEquals(50000, $other->wallet->fresh()->balance);
        $this->assertCount(0, $other->wallet->transactions);
    }

    public function test_admin_wallet_cannot_be_adjusted(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.users.wallet-adjust', $otherAdmin), [
                'direction' => 'credit',
                'amount' => 5000,
                'reason' => 'test',
            ])
            ->assertSessionHas('error');
    }
}
