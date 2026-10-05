<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\ProviderCountry;
use App\Models\ProviderService;
use App\Models\Service;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

/**
 * Weekly promotion: a temporary pricing layer over PricingService.
 * Promo prices are computed server-side per purchase, floored at
 * provider cost, and revert automatically when the window ends.
 * Mock HeroSMS: $0.50 cost → 300 XAF at the 600 XAF/USD test rate.
 */
class PromotionTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Setting::set('usd_to_xaf_rate', '600', 'float', 'pricing');
        Setting::set('default_markup_type', 'fixed', 'string', 'pricing');
        Setting::set('default_markup_value', '0', 'integer', 'pricing');
    }

    protected function promo(array $config = [], $startsAt = null, $endsAt = null, bool $enabled = true): Promotion
    {
        return Promotion::create([
            'name' => 'KamVerify Weekly Promo',
            'is_enabled' => $enabled,
            'starts_at' => $startsAt ?? now()->subHour(),
            'ends_at' => $endsAt ?? now()->addDays(7),
            'config' => array_merge([
                'facebook_price' => 750,
                'whatsapp_us_price' => 1500,
                'whatsapp_discount' => 1000,
                'telegram_price' => 800,
            ], $config),
        ]);
    }

    /** facebook service + mappings on the hero mock provider. */
    protected function service(string $slug, int $markup): Service
    {
        $provider = \App\Models\Provider::where('slug', 'herosms')->first()
            ?? \App\Models\Provider::latest('id')->first();

        $service = Service::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'is_active' => true,
            'customer_enabled' => true,
            'pricing_config' => ['mode' => 'fixed', 'markup' => $markup],
        ]);

        ProviderService::create([
            'provider_id' => $provider->id, 'service_id' => $service->id,
            'provider_service_code' => $slug, 'cost' => 0.50, 'is_active' => true,
        ]);

        foreach (Country::all() as $country) {
            ProviderCountry::firstOrCreate(
                ['provider_id' => $provider->id, 'country_id' => $country->id],
                ['provider_country_code' => $country->code, 'is_active' => true]
            );
        }

        return $service;
    }

    protected function country(string $code, string $name, int $markup = 0): Country
    {
        $c = Country::create([
            'name' => $name, 'code' => $code, 'dial_code' => '+1',
            'is_active' => true,
        ]);
        if ($markup > 0) {
            $c->update(['pricing_config' => ['markup' => $markup]]);
        }

        $provider = \App\Models\Provider::where('slug', 'herosms')->first();
        ProviderCountry::firstOrCreate(
            ['provider_id' => $provider->id, 'country_id' => $c->id],
            ['provider_country_code' => $code, 'is_active' => true]
        );

        return $c;
    }

    // ---------- Activation window ----------

    public function test_no_promotion_charges_normal_price(): void
    {
        [, $country, $wa] = $this->seedMarketplace();
        $wa->update(['pricing_config' => ['mode' => 'fixed', 'markup' => 2200]]);
        $user = $this->customer();

        $this->actingAs($user)->post('/orders', [
            'service_id' => $wa->id, 'country_id' => $country->id,
        ]);

        $order = Order::latest('id')->first();
        $this->assertEquals(2500, (float) $order->selling_price); // 300 + 2200
        $this->assertNull($order->promotion_id);
        $this->assertNull($order->normal_price);
    }

    public function test_active_promotion_charges_promo_price(): void
    {
        [$p, $country] = $this->seedMarketplace();
        $fb = $this->service('facebook', 1000);
        $promo = $this->promo();

        $q = app(PromotionService::class)->quote($fb, $country, 0.50);
        $this->assertSame(750, $q['price']);
        $this->assertSame(1300, $q['normal_price']);
        $this->assertSame(550, $q['discount_amount']);
        $this->assertSame($promo->id, $q['promotion_id']);
    }

    public function test_expired_promotion_restores_normal_price(): void
    {
        [$p, $country] = $this->seedMarketplace();
        $fb = $this->service('facebook', 1000);
        $this->promo([], now()->subDays(8), now()->subDay());

        $user = $this->customer();
        $this->actingAs($user)->post('/orders', [
            'service_id' => $fb->id, 'country_id' => $country->id,
        ]);

        $order = Order::latest('id')->first();
        $this->assertEquals(1300, (float) $order->selling_price);
        $this->assertNull($order->promotion_id);
    }

    public function test_scheduled_promotion_not_applied_yet(): void
    {
        [, $country] = $this->seedMarketplace();
        $fb = $this->service('facebook', 1000);
        $this->promo([], now()->addDay(), now()->addDays(8));

        $q = app(PromotionService::class)->quote($fb, $country, 0.50);
        $this->assertSame(1300, $q['price']);
        $this->assertNull($q['promotion_id']);
    }

    public function test_disabled_promotion_charges_normal_price(): void
    {
        [, $country] = $this->seedMarketplace();
        $fb = $this->service('facebook', 1000);
        $this->promo([], null, null, false);

        $q = app(PromotionService::class)->quote($fb, $country, 0.50);
        $this->assertSame(1300, $q['price']);
    }

    // ---------- Per-service prices ----------

    public function test_facebook_promo_price_is_750(): void
    {
        [, $country] = $this->seedMarketplace();
        $fb = $this->service('facebook', 1000);
        $this->promo();
        $user = $this->customer();

        $this->actingAs($user)->post('/orders', [
            'service_id' => $fb->id, 'country_id' => $country->id,
        ]);

        $order = Order::latest('id')->first();
        $this->assertEquals(750, (float) $order->selling_price);
        $this->assertEquals(1300, (float) $order->normal_price);
        $this->assertEquals(550, (float) $order->discount_amount);
        $this->assertNotNull($order->promotion_id);
    }

    public function test_usa_whatsapp_promo_price_is_1500(): void
    {
        [, $us, $wa] = $this->seedMarketplace();
        $wa->update(['pricing_config' => ['mode' => 'fixed', 'markup' => 2200]]);
        $this->promo();
        $user = $this->customer();

        $this->actingAs($user)->post('/orders', [
            'service_id' => $wa->id, 'country_id' => $us->id,
        ]);

        $order = Order::latest('id')->first();
        $this->assertEquals(1500, (float) $order->selling_price);
        $this->assertEquals(2500, (float) $order->normal_price);
        $this->assertEquals(1000, (float) $order->discount_amount);
    }

    public function test_whatsapp_discount_computed_from_real_normal_price(): void
    {
        [, $us, $wa] = $this->seedMarketplace();
        // No service markup — the US country carries a 2200 XAF markup:
        // live quote cost $0.50 → normal US price 300 + 2200 = 2500.
        $us->update(['pricing_config' => ['markup' => 2200]]);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post(route('admin.promotions.store'), [
            'name' => 'KamVerify Weekly Promo',
            'starts_at' => now()->format('Y-m-d\TH:i'),
            'facebook_price' => 750,
            'whatsapp_us_price' => 1500,
            'telegram_price' => 800,
            // whatsapp_discount left blank → auto-calculated
        ])->assertRedirect();

        $promo = Promotion::latest('id')->first();
        $this->assertSame(1000, $promo->price('whatsapp_discount')); // 2500 − 1500
    }

    public function test_same_absolute_discount_applies_to_other_whatsapp_countries(): void
    {
        [, $us, $wa] = $this->seedMarketplace();
        // Country-level markups (no service markup): US 2500, NG 1800.
        $us->update(['pricing_config' => ['markup' => 2200]]);
        $ng = $this->country('NG', 'Nigeria', 1500);
        $promo = $this->promo(['whatsapp_discount' => 1000]); // 2500 − 1500

        $q = app(PromotionService::class)->quote($wa, $ng, 0.50);
        $this->assertSame(1800, $q['normal_price']);  // 300 + 1500
        $this->assertSame(800, $q['price']);          // 1800 − 1000
        $this->assertSame(1000, $q['discount_amount']);
    }

    public function test_telegram_promo_price_is_800(): void
    {
        [, $country] = $this->seedMarketplace();
        $tg = $this->service('telegram', 1000);
        $this->promo();
        $user = $this->customer();

        $this->actingAs($user)->post('/orders', [
            'service_id' => $tg->id, 'country_id' => $country->id,
        ]);

        $order = Order::latest('id')->first();
        $this->assertEquals(800, (float) $order->selling_price);
        $this->assertEquals(1300, (float) $order->normal_price);
    }

    public function test_non_promoted_service_unchanged(): void
    {
        [, $country] = $this->seedMarketplace();
        $tt = $this->service('tiktok', 1000);
        $this->promo();

        $q = app(PromotionService::class)->quote($tt, $country, 0.50);
        $this->assertSame(1300, $q['price']);
        $this->assertNull($q['promotion_id']);
    }

    // ---------- Server-side enforcement / security ----------

    public function test_promo_price_enforced_server_side(): void
    {
        [, $country] = $this->seedMarketplace();
        $fb = $this->service('facebook', 1000);
        $this->promo();
        $user = $this->customer();

        // The form only submits service+country — price is never trusted.
        $this->actingAs($user)->post('/orders', [
            'service_id' => $fb->id, 'country_id' => $country->id,
        ]);

        $this->assertEquals(750, (float) Order::latest('id')->first()->selling_price);
    }

    public function test_browser_cannot_manipulate_price_or_promotion(): void
    {
        [, $country, $wa] = $this->seedMarketplace();
        $wa->update(['pricing_config' => ['mode' => 'fixed', 'markup' => 2200]]);
        $promo = $this->promo();
        $user = $this->customer();

        $this->actingAs($user)->post('/orders', [
            'service_id' => $wa->id, 'country_id' => $country->id,
            'price' => 1, 'promo_price' => 1, 'promotion_id' => 99999,
            'normal_price' => 1, 'discount_amount' => 500000,
        ]);

        $order = Order::latest('id')->first();
        $this->assertEquals(1500, (float) $order->selling_price);   // US WhatsApp promo
        $this->assertEquals(2500, (float) $order->normal_price);
        $this->assertSame($promo->id, $order->promotion_id);        // real promo, not forged id
        $this->assertEquals(50000 - 1500, (float) $user->wallet->fresh()->balance);
    }

    public function test_browser_cannot_extend_promotion(): void
    {
        [, $country, $wa] = $this->seedMarketplace();
        $wa->update(['pricing_config' => ['mode' => 'fixed', 'markup' => 2200]]);
        $promo = $this->promo([], now()->subDay(), now()->subMinute()); // expired
        $user = $this->customer();

        $this->actingAs($user)->post('/orders', [
            'service_id' => $wa->id, 'country_id' => $country->id,
            'ends_at' => now()->addYear()->toIso8601String(),
        ]);

        $order = Order::latest('id')->first();
        $this->assertEquals(2500, (float) $order->selling_price); // normal price
        $this->assertNull($order->promotion_id);
        $this->assertTrue($promo->fresh()->ends_at->isPast()); // untouched
    }

    // ---------- Wallet & accounting ----------

    public function test_wallet_deducts_exact_promo_price(): void
    {
        [, $country] = $this->seedMarketplace();
        $fb = $this->service('facebook', 1000);
        $this->promo();
        $user = $this->customer();

        $this->actingAs($user)->post('/orders', [
            'service_id' => $fb->id, 'country_id' => $country->id,
        ]);

        $this->assertEquals(50000 - 750, (float) $user->wallet->fresh()->balance);
        $txn = WalletTransaction::where('type', 'purchase')->latest('id')->first();
        $this->assertEquals(750, abs((float) $txn->amount)); // debits stored negative
    }

    public function test_refund_uses_actual_amount_paid(): void
    {
        [, $country] = $this->seedMarketplace();
        $fb = $this->service('facebook', 1000);
        $this->promo();
        $user = $this->customer();

        $this->actingAs($user)->post('/orders', [
            'service_id' => $fb->id, 'country_id' => $country->id,
        ]);

        $order = Order::latest('id')->first();
        app(\App\Services\OrderService::class)
            ->cancelOrder($order, true, false, 'test_refund', 'released');

        $order->refresh();
        $this->assertEquals(750, (float) $order->refund_amount); // not 1300 normal
        $this->assertEquals(50000, (float) $user->wallet->fresh()->balance);
        $this->assertEquals(1, WalletTransaction::where('type', 'refund')->count());
    }

    public function test_no_duplicate_purchase_transactions(): void
    {
        [, $country] = $this->seedMarketplace();
        $fb = $this->service('facebook', 1000);
        $this->promo();
        $user = $this->customer();

        // Sequential purchases are independent orders — the in-flight
        // cache lock covers concurrent submits; each completed purchase
        // must produce exactly one order and one purchase transaction.
        $this->actingAs($user)->post('/orders', [
            'service_id' => $fb->id, 'country_id' => $country->id,
        ]);
        $this->actingAs($user)->post('/orders', [
            'service_id' => $fb->id, 'country_id' => $country->id,
        ]);

        $this->assertEquals(2, Order::where('user_id', $user->id)->count());
        $this->assertEquals(2, WalletTransaction::where('type', 'purchase')->count());
        $this->assertEquals(50000 - 1500, (float) $user->wallet->fresh()->balance);
    }

    public function test_no_negative_margin_on_promo(): void
    {
        [, $country] = $this->seedMarketplace();
        $fb = $this->service('facebook', 1000);
        $this->promo();

        // Provider cost $5.00 → 3000 XAF — far above the 750 target.
        $q = app(PromotionService::class)->quote($fb, $country, 5.00);
        $this->assertSame(3000, $q['price']);     // floored at cost, not 750
        $this->assertSame(4000, $q['normal_price']);
        $this->assertNotNull($q['promotion_id']); // still a promo order — zero margin, never a loss
    }

    public function test_promo_never_above_normal_price(): void
    {
        [, $country] = $this->seedMarketplace();
        $fb = $this->service('facebook', 100); // markup 100 → normal 400 < 750 target
        $this->promo();

        $q = app(PromotionService::class)->quote($fb, $country, 0.50);
        $this->assertSame(400, $q['price']);
        $this->assertNull($q['promotion_id']); // no discount → not a promo order
    }

    // ---------- Existing orders & pricing untouched ----------

    public function test_old_orders_never_repriced(): void
    {
        [, $country, $wa] = $this->seedMarketplace();
        $wa->update(['pricing_config' => ['mode' => 'fixed', 'markup' => 2200]]);
        $user = $this->customer();

        $this->actingAs($user)->post('/orders', [
            'service_id' => $wa->id, 'country_id' => $country->id,
        ]);
        $old = Order::latest('id')->first();
        $this->assertEquals(2500, (float) $old->selling_price);

        $this->promo();

        $this->actingAs($user)->post('/orders', [
            'service_id' => $wa->id, 'country_id' => $country->id,
        ]);
        $new = Order::latest('id')->first();

        $old->refresh();
        $this->assertEquals(2500, (float) $old->selling_price);   // untouched
        $this->assertNull($old->promotion_id);
        $this->assertEquals(1500, (float) $new->selling_price);   // new order is promo
    }

    public function test_normal_pricing_config_unchanged_by_promotion(): void
    {
        [, $country, $wa] = $this->seedMarketplace();
        $wa->update(['pricing_config' => ['mode' => 'fixed', 'markup' => 2200]]);
        $promo = $this->promo();

        $wa->refresh();
        $this->assertSame(2200, (int) $wa->pricing_config['markup']);
        $this->assertSame('fixed', $wa->pricing_config['mode']);
        $this->assertSame(2500, app(\App\Services\PricingService::class)
            ->calculateSellingPrice(0.50, $country, $wa));
    }

    // ---------- Auto-expiry / countdown ----------

    public function test_promotion_expires_automatically(): void
    {
        $this->seedMarketplace();
        $active = $this->promo();
        $this->assertNotNull(app(PromotionService::class)->current());
        $this->assertTrue($active->isActive());

        $expired = $this->promo([], now()->subDays(8), now()->subDay());
        $this->assertFalse($expired->isActive());
        $this->assertSame('expired', $expired->status());
    }

    public function test_landing_page_renders_banner_with_real_end_time(): void
    {
        $this->seedMarketplace();
        $promo = $this->promo();

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('WEEKLY PROMO', strtoupper($html));
        $this->assertStringContainsString('exclusive prices', $html);
        $this->assertStringContainsString($promo->ends_at->toIso8601String(), $html); // real countdown target
        $this->assertStringContainsString('kvPromo', $html);
        // Responsive: mobile column layout → desktop row
        $this->assertStringContainsString('md:flex-row', $html);
    }

    public function test_landing_page_hides_banner_when_inactive(): void
    {
        $this->seedMarketplace();
        $this->assertStringNotContainsString('WEEKLY PROMO', strtoupper($this->get('/')->getContent()));

        $this->promo([], now()->subDays(8), now()->subDay()); // expired
        $this->assertStringNotContainsString('WEEKLY PROMO', strtoupper($this->get('/')->getContent()));

        $this->promo([], null, null, false); // disabled
        $this->assertStringNotContainsString('WEEKLY PROMO', strtoupper($this->get('/')->getContent()));
    }

    public function test_dashboard_shows_compact_promo_card(): void
    {
        $this->seedMarketplace();
        $this->promo();
        $user = $this->customer();

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('WEEKLY PROMO', strtoupper($html));
        $this->assertStringContainsString('Shop now', $html);
        $this->assertStringContainsString('Ends in', $html);
    }

    public function test_dashboard_hides_card_when_inactive(): void
    {
        $this->seedMarketplace();
        $user = $this->customer();
        $this->assertStringNotContainsString('WEEKLY PROMO',
            strtoupper($this->actingAs($user)->get('/dashboard')->getContent()));
    }

    // ---------- Quote endpoint reflects promo ----------

    public function test_quote_returns_promo_pricing_fields(): void
    {
        [, $country] = $this->seedMarketplace();
        $fb = $this->service('facebook', 1000);
        $this->promo();

        $json = $this->actingAs($this->customer())
            ->getJson("/orders/quote?country_id={$country->id}&service_id={$fb->id}")
            ->assertOk()->json();

        $this->assertSame(750, $json['price']);
        $this->assertTrue($json['is_promo']);
        $this->assertSame(1300, $json['normal_price']);
        $this->assertSame('550 XAF', $json['discount_formatted']);
        // internal fields still not leaked
        foreach (['provider_cost', 'cost', 'markup', 'profit', 'provider'] as $leak) {
            $this->assertArrayNotHasKey($leak, $json);
        }
    }

    // ---------- Admin ----------

    public function test_admin_can_create_seven_day_promotion(): void
    {
        $this->seedMarketplace();
        $start = now()->startOfHour();

        $this->actingAs($this->admin(), 'admin')->post(route('admin.promotions.store'), [
            'name' => 'KamVerify Weekly Promo',
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'facebook_price' => 750,
            'whatsapp_us_price' => 1500,
            'telegram_price' => 800,
            'whatsapp_discount' => 1200,
        ])->assertRedirect(route('admin.promotions.index'));

        $promo = Promotion::latest('id')->first();
        $this->assertSame('KamVerify Weekly Promo', $promo->name);
        $this->assertEquals(7, $promo->starts_at->diffInDays($promo->ends_at)); // exactly one week
        $this->assertSame(750, $promo->price('facebook_price'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'promotion.create', 'model_id' => $promo->id]);
    }

    public function test_admin_can_toggle_promotion(): void
    {
        $this->seedMarketplace();
        $promo = $this->promo();
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.promotions.toggle', $promo))->assertRedirect();
        $this->assertFalse($promo->fresh()->is_enabled);
        $this->assertNull(app(PromotionService::class)->current());

        $this->actingAs($admin, 'admin')
            ->put(route('admin.promotions.toggle', $promo))->assertRedirect();
        $this->assertTrue($promo->fresh()->is_enabled);
    }

    public function test_customer_cannot_manage_promotions(): void
    {
        $this->seedMarketplace();
        $promo = $this->promo();

        $this->actingAs($this->customer())
            ->post(route('admin.promotions.store'), [
                'name' => 'x', 'starts_at' => now()->format('Y-m-d\TH:i'),
                'facebook_price' => 1, 'whatsapp_us_price' => 1, 'telegram_price' => 1,
            ]);
        $this->assertEquals(1, Promotion::count()); // only the seeded one

        $this->actingAs($this->customer())
            ->put(route('admin.promotions.toggle', $promo));
        $this->assertTrue($promo->fresh()->is_enabled);
    }

    public function test_admin_index_shows_promo_stats(): void
    {
        [, $country] = $this->seedMarketplace();
        $fb = $this->service('facebook', 1000);
        $promo = $this->promo();
        $user = $this->customer();

        $this->actingAs($user)->post('/orders', [
            'service_id' => $fb->id, 'country_id' => $country->id,
        ]);

        $html = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.promotions.index'))->assertOk()->getContent();

        $this->assertStringContainsString('KamVerify Weekly Promo', $html);
        $this->assertStringContainsString('Active', $html);
        $this->assertStringContainsString('750 XAF', $html); // promo revenue + facebook price
    }
}
