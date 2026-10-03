<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use App\Services\ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

/**
 * Reconciliation engine + admin reporting: historical transaction
 * classification, per-customer ledger integrity, provider-cost
 * recovery, ambiguity flagging, and the admin page.
 */
class ReconciliationTest extends TestCase
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

    public function test_reconcile_classifies_legacy_refund_as_refund(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        // Simulate a pre-fix refund: type 'deposit', desc 'Order refund'
        $legacy = WalletTransaction::create([
            'transaction_id' => 'TXN-LEGACY1',
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'amount' => $order->selling_price,
            'type' => 'deposit',
            'status' => 'completed',
            'description' => 'Order refund',
            'metadata' => ['order_id' => $order->order_id, 'refund_type' => 'order_cancellation'],
        ]);
        $user->wallet->increment('balance', (float) $order->selling_price);
        $user->wallet->increment('total_deposited', (float) $order->selling_price);

        $report = app(ReconciliationService::class)->buildReport();
        $proposal = collect($report['transactions']['proposals'])
            ->firstWhere('transaction.id', $legacy->id);

        $this->assertNotNull($proposal);
        $this->assertSame('deposit', $proposal['from']);
        $this->assertSame('refund', $proposal['to']);
    }

    public function test_reconcile_apply_retypes_and_audits(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        $legacy = WalletTransaction::create([
            'transaction_id' => 'TXN-LEGACY2',
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'amount' => $order->selling_price,
            'type' => 'deposit',
            'status' => 'completed',
            'description' => 'Order refund',
            'metadata' => ['refund_type' => 'order_cancellation'],
        ]);

        $this->artisan('wallet:reconcile', ['--apply' => true])->assertSuccessful();

        $this->assertSame('refund', $legacy->fresh()->type);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reconcile.transaction_type',
            'model_id' => $legacy->id,
        ]);
    }

    public function test_ambiguous_records_are_flagged_never_changed(): void
    {
        $user = $this->customer();

        // A credit with no payment link, no order link, no known
        // description — unclassifiable. Must be flagged, not guessed.
        $mystery = WalletTransaction::create([
            'transaction_id' => 'TXN-MYSTERY',
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'amount' => 4000,
            'type' => 'deposit',
            'status' => 'completed',
            'description' => 'Unexplained credit',
        ]);

        $report = app(ReconciliationService::class)->buildReport();
        $this->assertCount(1, $report['transactions']['ambiguous']);
        $this->assertSame($mystery->id, $report['transactions']['ambiguous'][0]['transaction']->id);

        // Apply must not touch it.
        $this->artisan('wallet:reconcile', ['--apply' => true])->assertSuccessful();
        $this->assertSame('deposit', $mystery->fresh()->type);
    }

    public function test_customer_balance_integrity_detects_discrepancy(): void
    {
        $user = $this->customer(); // balance 50,000, no matching ledger rows

        $report = app(ReconciliationService::class)->buildReport();
        $row = collect($report['customers'])->firstWhere('user.id', $user->id);

        $this->assertNotNull($row);
        $this->assertFalse($row['match']);            // 50,000 balance vs 0 ledger
        $this->assertEquals(50000.0, $row['difference']);
    }

    public function test_clean_customer_reconciles(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();

        // Zero the fixture balance so only real txns back it
        $user->wallet->update(['balance' => 0, 'total_deposited' => 0]);

        app(\App\Services\WalletService::class)->deposit($user, 10000, 'Wallet deposit — test', null, 'deposit');
        $user = $user->fresh(); // drop the cached wallet relation (balance was 0)
        $this->buy($user, $country, $service);

        $report = app(ReconciliationService::class)->buildReport();
        $row = collect($report['customers'])->firstWhere('user.id', $user->id);

        $this->assertTrue($row['match']);
    }

    public function test_provider_reconciliation_shows_cost_recovery(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        app(OrderService::class)->cancelOrder($order, true, false, 'customer_cancel', 'released');

        $report = app(ReconciliationService::class)->buildReport();
        $pr = $report['provider'];

        $this->assertSame(1, $pr['activations']);
        $this->assertSame(1, $pr['provider_released']);
        $this->assertEquals((float) $order->purchase_price, $pr['recovered_cost']);
        $this->assertSame(0, $pr['provider_consumed']);
    }

    public function test_admin_reconciliation_page_loads_and_flags_discrepancy(): void
    {
        $admin = $this->admin();
        $user = $this->customer(); // fixture wallet has 50k with no ledger rows → discrepancy

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reconciliation.index'))
            ->assertOk()
            ->assertSee('DISCREPANCY')
            ->assertSee($user->email);
    }

    public function test_reconciliation_requires_admin(): void
    {
        $this->get(route('admin.reconciliation.index'))->assertRedirect();
        $res = $this->actingAs($this->customer())
            ->get(route('admin.reconciliation.index'));
        $this->assertContains($res->status(), [302, 403]);
    }

    public function test_refundable_outstanding_metric_flags_owed_refunds(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        // End the order WITHOUT a refund (simulating a stuck cancel)
        $order->update(['status' => 'cancelled', 'refund_amount' => 0]);

        $overview = app(\App\Services\AnalyticsService::class)->dashboardOverview();
        $this->assertEquals((float) $order->selling_price, $overview['refundable_outstanding']);
    }

    public function test_admin_apply_button_retypes_via_audited_path(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $admin = $this->admin();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        $legacy = WalletTransaction::create([
            'transaction_id' => 'TXN-LEGACY4',
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'amount' => $order->selling_price,
            'type' => 'deposit',
            'status' => 'completed',
            'description' => 'Order refund',
            'metadata' => ['refund_type' => 'order_cancellation'],
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.reconciliation.apply'))
            ->assertSessionHas('success');

        $this->assertSame('refund', $legacy->fresh()->type);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reconcile.transaction_type',
            'model_id' => $legacy->id,
        ]);
    }

    public function test_dry_run_changes_nothing(): void
    {
        [$p, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();
        $order = $this->buy($user, $country, $service);

        WalletTransaction::create([
            'transaction_id' => 'TXN-LEGACY3',
            'user_id' => $user->id,
            'wallet_id' => $user->wallet->id,
            'amount' => $order->selling_price,
            'type' => 'deposit',
            'status' => 'completed',
            'description' => 'Order refund',
            'metadata' => ['refund_type' => 'order_cancellation'],
        ]);

        $this->artisan('wallet:reconcile')->assertSuccessful();

        $this->assertSame('deposit',
            WalletTransaction::where('transaction_id', 'TXN-LEGACY3')->first()->type);
    }
}
