<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\Setting;
use App\Services\AnalyticsService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

class PricingLayerTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        // Deterministic rate: $1 = 600 XAF; fixed-markup default mode.
        Setting::set('usd_to_xaf_rate', '600', 'float', 'pricing');
        Setting::set('default_markup_type', 'fixed', 'string', 'pricing');
        Setting::set('default_markup_value', '0', 'integer', 'pricing');
    }

    // ---------- Customer price = provider cost + fixed XAF markup ----------

    public function test_fixed_markup_per_service(): void
    {
        $pricing = app(PricingService::class);

        $mk = fn (string $slug, int $markup) => Service::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'is_active' => true,
            'customer_enabled' => true,
            'pricing_config' => ['mode' => 'fixed', 'markup' => $markup],
        ]);
        $fb = $mk('facebook', 1000);
        $wa = $mk('whatsapp', 1500);
        $tg = $mk('telegram', 1000);
        $tt = $mk('tiktok', 1000);

        // Provider cost $0.50 -> 300 XAF base
        $this->assertSame(1300, $pricing->calculateSellingPrice(0.50, null, $fb));
        $this->assertSame(1800, $pricing->calculateSellingPrice(0.50, null, $wa));
        $this->assertSame(1300, $pricing->calculateSellingPrice(0.50, null, $tg));
        $this->assertSame(1300, $pricing->calculateSellingPrice(0.50, null, $tt));
    }

    public function test_customer_price_follows_actual_country_provider_cost(): void
    {
        $pricing = app(PricingService::class);
        $fb = Service::create(['name' => 'Facebook', 'slug' => 'facebook', 'is_active' => true, 'customer_enabled' => true, 'pricing_config' => ['mode' => 'fixed', 'markup' => 1000]]);
        $us = \App\Models\Country::create(['name' => 'United States', 'code' => 'US', 'dial_code' => '+1', 'is_active' => true]);
        $cm = \App\Models\Country::create(['name' => 'Cameroon', 'code' => 'CM', 'dial_code' => '+237', 'is_active' => true]);

        // Different provider costs per country -> different customer prices
        $this->assertSame(1960, $pricing->calculateSellingPrice(1.60, $us, $fb)); // 960 + 1000
        $this->assertSame(1090, $pricing->calculateSellingPrice(0.15, $us, $fb)); // 90 + 1000
        $this->assertSame(1030, $pricing->calculateSellingPrice(0.05, $cm, $fb)); // 30 + 1000
    }

    public function test_service_mode_overrides_global_default(): void
    {
        $pricing = app(PricingService::class);
        Setting::set('default_markup_type', 'percentage', 'string', 'pricing');
        Setting::set('default_markup_value', '30', 'integer', 'pricing');

        // Service pinned to fixed ignores the global percentage mode
        $fb = Service::create(['name' => 'Facebook', 'slug' => 'facebook', 'is_active' => true, 'pricing_config' => ['mode' => 'fixed', 'markup' => 1000]]);
        $this->assertSame(1600, $pricing->calculateSellingPrice(1.00, null, $fb)); // 600 + 1000

        // Service pinned to percentage uses % even under global fixed
        Setting::set('default_markup_type', 'fixed', 'string', 'pricing');
        $pct = Service::create(['name' => 'Pct', 'slug' => 'pct', 'is_active' => true, 'pricing_config' => ['mode' => 'percentage', 'markup' => 50]]);
        $this->assertSame(900, $pricing->calculateSellingPrice(1.00, null, $pct)); // 600 * 1.5
    }

    // ---------- Order locks price fields ----------

    public function test_order_locks_customer_price_provider_cost_and_profit(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $service->update(['pricing_config' => ['mode' => 'fixed', 'markup' => 1500]]);
        $user = $this->customer();

        $this->actingAs($user)->post('/orders', [
            'service_id' => $service->id,
            'country_id' => $country->id,
        ]);

        $order = Order::latest('id')->first();
        // Mock provider cost = $0.50 -> 300 XAF base + 1500 markup
        $this->assertEquals(1800, (float) $order->selling_price);
        $this->assertEquals(0.50, (float) $order->purchase_price);
        $this->assertEquals(1500, (float) $order->profit);
        $this->assertEquals(50000 - 1800, (float) $user->wallet->fresh()->balance);
    }

    public function test_quote_exposes_customer_price_only(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $service->update(['pricing_config' => ['mode' => 'fixed', 'markup' => 1500]]);

        $response = $this->actingAs($this->customer())
            ->getJson("/orders/quote?country_id={$country->id}&service_id={$service->id}");

        $response->assertOk()->assertJson(['available' => true, 'price' => 1800]);
        $json = $response->json();
        foreach (['provider_cost', 'cost', 'markup', 'profit', 'provider'] as $leak) {
            $this->assertArrayNotHasKey($leak, $json, "Quote leaked internal field: {$leak}");
        }
    }

    // ---------- Admin price management ----------

    public function test_admin_can_update_service_markup_and_mode(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')
            ->put(route('admin.pricing.services.update', $service), [
                'markup' => 2000,
                'mode' => 'fixed',
            ]);

        $response->assertSessionHas('success');
        $service->refresh();
        $this->assertEquals(2000, $service->pricing_config['markup']);
        $this->assertSame('fixed', $service->pricing_config['mode']);

        // New customer price reflects the new markup: 300 + 2000
        $this->assertSame(2300, app(PricingService::class)->calculateSellingPrice(0.50, $country, $service));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pricing.service.update',
            'model_id' => $service->id,
        ]);
    }

    public function test_pricing_rejects_negative_markup_and_bad_mode(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.pricing.services.update', $service), ['markup' => -5])
            ->assertSessionHasErrors('markup');

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.pricing.services.update', $service), ['markup' => 100, 'mode' => 'bogus'])
            ->assertSessionHasErrors('mode');
    }

    public function test_customer_cannot_access_pricing_admin(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();

        $response = $this->actingAs($this->customer())
            ->put(route('admin.pricing.services.update', $service), ['markup' => 1]);

        $this->assertNotSame(200, $response->status());
        $this->assertNull($service->fresh()->pricing_config);
    }

    // ---------- Profit reporting ----------

    public function test_refunded_orders_do_not_count_as_profit(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $service->update(['pricing_config' => ['mode' => 'fixed', 'markup' => 1000]]);

        $a = $this->customer();
        $b = $this->customer();

        // Order that stays a sale
        $this->actingAs($a)->post('/orders', ['service_id' => $service->id, 'country_id' => $country->id]);
        // Order that gets cancelled/refunded
        $this->actingAs($b)->post('/orders', ['service_id' => $service->id, 'country_id' => $country->id]);
        $refunded = Order::where('user_id', $b->id)->latest('id')->first();
        $refunded->update(['status' => 'refunded', 'refund_amount' => $refunded->selling_price]);

        $metrics = app(AnalyticsService::class)->businessMetrics(now()->subDay(), now()->addDay());

        // Only the non-refunded order counts: 300 + 1000 = 1300 revenue, 1000 profit
        $this->assertEquals(1300, $metrics['revenue']['gross_sales']);
        $this->assertEquals(1000, $metrics['profit']['gross_profit']);
        $this->assertEquals(1300, $metrics['revenue']['refunds']);
    }
}
