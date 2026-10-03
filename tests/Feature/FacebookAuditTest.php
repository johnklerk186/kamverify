<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Order;
use App\Models\Service;
use App\Services\FacebookAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

/**
 * Facebook/Meta compatibility audit — verifies the read-only analysis:
 * outcome bucketing, prefix failure clustering, financial roll-up,
 * masking, and the admin page. No provider calls are made.
 */
class FacebookAuditTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    protected function fbOrder($user, $country, $service, $provider, array $attrs): Order
    {
        static $n = 0;
        $n++;
        return Order::create(array_merge([
            'order_id' => 'KV-FB-' . $n,
            'user_id' => $user->id,
            'country_id' => $country->id,
            'service_id' => $service->id,
            'provider_id' => $provider->id,
            'status' => 'cancelled',
            'selling_price' => 1500,
            'purchase_price' => 0.50,
            'refund_amount' => 0,
        ], $attrs));
    }

    protected function fbService(): Service
    {
        return Service::create([
            'name' => 'Facebook',
            'slug' => 'facebook',
            'icon' => 'facebook',
            'is_active' => true,
            'customer_enabled' => true,
        ]);
    }

    public function test_report_buckets_outcomes_and_financials(): void
    {
        [$p, $country] = $this->seedMarketplace();
        $fb = $this->fbService();
        $user = $this->customer();

        // 2 successful (prefix +1415)
        $this->fbOrder($user, $country, $fb, $p, [
            'status' => 'completed', 'phone_number' => '+14155550101',
            'provider_activation_id' => 'A1', 'completed_at' => now(),
        ]);
        $this->fbOrder($user, $country, $fb, $p, [
            'status' => 'completed', 'phone_number' => '+14155550102',
            'provider_activation_id' => 'A2', 'completed_at' => now(),
        ]);

        // 6 no-SMS failures on a different prefix (+7911), provider released
        foreach (range(3, 8) as $i) {
            $this->fbOrder($user, $country, $fb, $p, [
                'status' => 'expired', 'phone_number' => "+791155501{$i}0",
                'provider_activation_id' => "A{$i}", 'refund_amount' => 1500,
                'provider_refund_status' => 'released',
            ]);
        }

        // 1 consumed-cost failure + 1 pre-activation failure
        $this->fbOrder($user, $country, $fb, $p, [
            'phone_number' => '+79115550199', 'provider_activation_id' => 'A9',
            'refund_amount' => 1500, 'provider_refund_status' => 'consumed',
        ]);
        $this->fbOrder($user, $country, $fb, $p, [
            'status' => 'failed', 'cancellation_reason' => 'provider_purchase_failed',
        ]);

        $r = app(FacebookAuditService::class)->buildReport();
        $t = $r['totals'];

        $this->assertSame(10, $t['orders']);
        $this->assertSame(2, $t['completed']);
        $this->assertSame(7, $t['failed_no_sms']);
        $this->assertEquals(20.0, $t['success_rate']);
        $this->assertEquals(10500.0, $t['refunds_xaf']);
        $this->assertEquals(0.50, $t['cost_consumed_usd']);
        $this->assertEquals(3.00, $t['cost_recovered_usd']);
        $this->assertEquals(0.50, $t['kamverify_loss_usd']);

        // +7911 must cluster as a 0%-success prefix with adequate sample
        $bad = $r['prefixes']->firstWhere('prefix', '7911');
        $this->assertSame(7, $bad['total']);
        $this->assertEquals(0.0, $bad['success_rate']);
        $this->assertFalse($bad['low_confidence']);

        $good = $r['prefixes']->firstWhere('prefix', '1415');
        $this->assertEquals(100.0, $good['success_rate']);
    }

    public function test_small_prefix_samples_flagged_low_confidence(): void
    {
        [$p, $country] = $this->seedMarketplace();
        $fb = $this->fbService();
        $user = $this->customer();

        $this->fbOrder($user, $country, $fb, $p, [
            'status' => 'cancelled', 'phone_number' => '+447700900123',
            'provider_activation_id' => 'B1',
        ]);

        $r = app(FacebookAuditService::class)->buildReport();
        $bucket = $r['prefixes']->firstWhere('prefix', '4477');

        $this->assertTrue($bucket['low_confidence']);
    }

    public function test_phone_numbers_are_masked(): void
    {
        [$p, $country] = $this->seedMarketplace();
        $fb = $this->fbService();
        $user = $this->customer();

        $order = $this->fbOrder($user, $country, $fb, $p, [
            'status' => 'completed', 'phone_number' => '+14155551234',
            'provider_activation_id' => 'C1',
        ]);

        $r = app(FacebookAuditService::class)->buildReport();
        $row = $r['orders']->firstWhere('order.id', $order->id);

        $this->assertStringNotContainsString('55551234', $row['masked_phone']);
        $this->assertStringContainsString('14155', $row['masked_phone']); // prefix survives
    }

    public function test_admin_page_renders_report(): void
    {
        [$p, $country] = $this->seedMarketplace();
        $fb = $this->fbService();
        $admin = $this->admin();
        $user = $this->customer();

        $this->fbOrder($user, $country, $fb, $p, [
            'status' => 'completed', 'phone_number' => '+14155550101',
            'provider_activation_id' => 'D1',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reconciliation.facebook'))
            ->assertOk()
            ->assertSee('Facebook/Meta compatibility audit')
            ->assertSee('SMS delivered');
    }

    public function test_facebook_audit_requires_admin(): void
    {
        $this->get(route('admin.reconciliation.facebook'))->assertRedirect();
        $res = $this->actingAs($this->customer())
            ->get(route('admin.reconciliation.facebook'));
        $this->assertContains($res->status(), [302, 403]);
    }
}
