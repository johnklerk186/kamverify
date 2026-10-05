<?php

namespace Tests\Feature;

use App\Exceptions\TextVerifiedException;
use App\Jobs\CancelOrderJob;
use App\Jobs\CheckSmsJob;
use App\Jobs\ExpireOrderJob;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderCountry;
use App\Models\ProviderLog;
use App\Models\ProviderService;
use App\Models\Service;
use App\Models\WalletTransaction;
use App\Services\Providers\HeroSmsProvider;
use App\Services\Providers\TextVerifiedProvider;
use App\Services\ProviderService as ProviderRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

/**
 * TextVerified fulfils ONLY Facebook/Meta — HeroSMS stays the provider for
 * WhatsApp, Telegram and TikTok. Live-mode tests fake every HTTP call and
 * mock mode is sandboxed, so no real TextVerified credits are ever spent.
 */
class TextVerifiedProviderTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    /**
     * Seed HeroSMS (whatsapp/US + facebook mappings on NG/CM/GB) and a
     * TextVerified provider wired to facebook for US only via the
     * country-scoped routing override.
     *
     * @return array{Service, \App\Models\Country, Provider, Provider, Service, \App\Models\User, array}
     */
    private function enableTextVerified(): array
    {
        [$hero, $us, $wa] = $this->seedMarketplace();
        $customer = $this->customer();

        $fb = Service::create([
            'name' => 'Facebook / Meta', 'slug' => 'facebook',
            'is_active' => true, 'customer_enabled' => true,
            'pricing_config' => ['mode' => 'fixed', 'markup' => 1000],
            'provider_mapping' => ['provider' => 'textverified', 'countries' => ['US']],
        ]);
        // The registration migration may already have created the row.
        $tv = Provider::firstOrCreate(
            ['slug' => 'textverified'],
            ['name' => 'TextVerified', 'base_url' => 'https://www.textverified.com',
                'is_active' => true]
        );
        ProviderService::firstOrCreate(
            ['provider_id' => $tv->id, 'service_id' => $fb->id],
            ['provider_service_code' => 'facebook', 'is_active' => true]
        );
        ProviderCountry::firstOrCreate(
            ['provider_id' => $tv->id, 'country_id' => $us->id],
            ['provider_country_code' => 'US', 'is_active' => true]
        );

        // HeroSMS keeps facebook outside the US.
        ProviderService::firstOrCreate(
            ['provider_id' => $hero->id, 'service_id' => $fb->id],
            ['provider_service_code' => 'fb', 'is_active' => true]
        );
        $others = [];
        foreach ([['Nigeria', 'NG', '19'], ['Cameroon', 'CM', '41'], ['United Kingdom', 'GB', '16']] as [$name, $code, $heroId]) {
            $c = \App\Models\Country::create([
                'name' => $name, 'code' => $code, 'dial_code' => '+0', 'is_active' => true,
            ]);
            ProviderCountry::create([
                'provider_id' => $hero->id, 'country_id' => $c->id,
                'provider_country_code' => $heroId, 'is_active' => true,
            ]);
            $others[$code] = $c;
        }

        return [$fb, $us, $tv, $hero, $wa, $customer, $others];
    }

    /** A live-mode provider against faked HTTP. */
    private function liveTv(): TextVerifiedProvider
    {
        config(['services.textverified' => [
            'enabled' => true, 'mode' => 'production',
            'base_url' => 'https://www.textverified.com',
            'api_key' => 'tv-secret-key', 'username' => 'ops@example.com',
            'timeout' => 10,
        ]]);

        return new TextVerifiedProvider();
    }

    private function fakeAuth(string $token = 'tv-token-1'): array
    {
        return ['*/api/pub/v2/auth' => Http::response([
            'token' => $token,
            'expiresAt' => now()->addHour()->toIso8601String(),
        ])];
    }

    public function test_facebook_us_routes_to_textverified_other_countries_to_herosms(): void
    {
        [$fb, $us, $tv, $hero, $wa, $customer, $others] = $this->enableTextVerified();

        $svc = app(ProviderRouter::class);
        $this->assertSame('textverified', $svc->providerFor($fb, $us)->slug);

        // Every non-US Facebook country stays on HeroSMS.
        foreach ($others as $code => $country) {
            $this->assertSame('herosms', $svc->providerFor($fb, $country)->slug, "facebook+{$code}");
        }

        // Other services are never routed to TextVerified — US included.
        $this->assertSame('herosms', $svc->providerFor($wa, $us)->slug);
        $this->assertSame('herosms', $svc->providerFor($wa)->slug);

        foreach (['telegram', 'tiktok'] as $slug) {
            $svc2 = Service::create(['name' => ucfirst($slug), 'slug' => $slug,
                'is_active' => true, 'customer_enabled' => true]);
            $this->assertSame('herosms', $svc->providerFor($svc2, $us)->slug);
        }

        $this->assertInstanceOf(TextVerifiedProvider::class, $svc->getProviderForModel($tv));
        $this->assertInstanceOf(HeroSmsProvider::class, $svc->getProviderForModel($hero));
    }

    public function test_facebook_non_us_purchase_uses_herosms(): void
    {
        [$fb, $us, $tv, $hero, $wa, $customer, $others] = $this->enableTextVerified();
        $ng = $others['NG'];

        // Quote: HeroSMS mock cost $0.50 → 300 XAF + 1000 XAF markup = 1300.
        $quote = $this->actingAs($customer)
            ->getJson(route('orders.quote', ['service_id' => $fb->id, 'country_id' => $ng->id]))
            ->assertOk();
        $this->assertEquals(1300, $quote->json('price'));

        $balBefore = $customer->wallet->balance;
        $this->actingAs($customer)
            ->post(route('orders.store'), ['service_id' => $fb->id, 'country_id' => $ng->id])
            ->assertRedirect();

        $order = Order::where('service_id', $fb->id)->latest()->first();
        $this->assertEquals('herosms', $order->provider->slug);
        $this->assertStringStartsWith('ACT-', $order->provider_activation_id);
        $this->assertEquals(1300, $order->selling_price);
        $this->assertEquals($balBefore - 1300, $customer->wallet->fresh()->balance);
    }

    public function test_facebook_country_list_covers_us_and_herosms_countries(): void
    {
        [$fb, $us, $tv, $hero, $wa, $customer, $others] = $this->enableTextVerified();

        $countries = collect($this->actingAs($customer)
            ->getJson(route('orders.countries', ['service_id' => $fb->id]))
            ->assertOk()
            ->json('countries'));

        $codes = $countries->pluck('code')->all();
        $this->assertContains('US', $codes);
        $this->assertContains('NG', $codes);
        $this->assertContains('CM', $codes);
        $this->assertContains('GB', $codes);
        // Provider internals are never exposed to the customer.
        $this->assertStringNotContainsString('textverified', strtolower(json_encode($countries)));
    }

    public function test_facebook_us_fails_closed_when_textverified_unconfigured(): void
    {
        [$fb, $us, $tv, $hero, $wa, $customer, $others] = $this->enableTextVerified();

        // Production mode, no credentials — HeroSMS must NOT pick it up.
        config(['services.textverified' => ['enabled' => true, 'mode' => 'production',
            'api_key' => '', 'username' => '', 'base_url' => 'https://www.textverified.com']]);
        Http::fake(['*' => Http::response('unauthorized', 401)]);

        $this->actingAs($customer)
            ->post(route('orders.store'), ['service_id' => $fb->id, 'country_id' => $us->id]);

        $this->assertEquals(0, Order::where('service_id', $fb->id)
            ->whereNotNull('provider_activation_id')->count());
        // The only order created (if any) was wound back refunded.
        $this->assertEquals(0, Order::where('service_id', $fb->id)
            ->where('status', 'waiting_for_sms')->count());
        $this->assertEquals($customer->wallet->balance, $customer->wallet->fresh()->balance);
    }

    public function test_bearer_token_is_generated_cached_and_refreshed(): void
    {
        Cache::flush();
        $this->enableTextVerified();

        $authCount = 0;
        Http::fake(function ($req) use (&$authCount) {
            if (str_contains($req->url(), '/api/pub/v2/auth')) {
                $authCount++;
                return Http::response([
                    'token' => 'tv-token-' . $authCount,
                    'expiresAt' => now()->addHour()->toIso8601String(),
                ]);
            }
            return Http::response(['currentBalance' => 42.5]);
        });

        $p = $this->liveTv();
        $this->assertEquals(42.5, $p->getBalance());
        $this->assertEquals('tv-token-1', Cache::get('textverified:bearer')['token']);
        $this->assertEquals(1, $authCount);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/auth')
            && $r->header('X-API-KEY')[0] === 'tv-secret-key'
            && $r->header('X-API-USERNAME')[0] === 'ops@example.com');

        // Second call reuses the cached token — the API request goes out
        // but NO new /auth request is minted.
        $p->getBalance();
        $this->assertEquals(1, $authCount);

        // Force expiry → a fresh token is minted.
        Cache::put('textverified:bearer', ['token' => 'old', 'expires_at' => time() - 1], 60);
        $p->getBalance();
        $this->assertEquals(2, $authCount);
        $this->assertEquals('tv-token-2', Cache::get('textverified:bearer')['token']);
    }

    public function test_live_verification_creation_assigns_number_and_cost(): void
    {
        $this->enableTextVerified();
        Http::fake(array_merge($this->fakeAuth(), [
            '*/api/pub/v2/verifications' => Http::response([
                'id' => 'ver_123', 'number' => '+12025550123',
                'totalCost' => 0.75, 'state' => 'VERIFICATION_PENDING',
            ]),
        ]));

        $r = $this->liveTv()->purchaseNumber('US', 'facebook');

        $this->assertEquals('success', $r['status']);
        $this->assertEquals('ver_123', $r['activation_id']);
        $this->assertEquals('+12025550123', $r['phone_number']);
        $this->assertEquals(0.75, $r['cost']);
    }

    public function test_national_format_number_is_coerced_to_us_e164(): void
    {
        $this->enableTextVerified();
        Http::fake(array_merge($this->fakeAuth(), [
            '*/api/pub/v2/verifications' => Http::response([
                'id' => 'ver_10digit', 'number' => '2025550123', // national format
                'totalCost' => 0.75, 'state' => 'VERIFICATION_PENDING',
            ]),
        ]));

        $r = $this->liveTv()->purchaseNumber('US', 'facebook');
        $this->assertEquals('+12025550123', $r['phone_number']); // not +2025550123
    }

    public function test_facebook_order_fulfilled_via_textverified_end_to_end(): void
    {
        [$fb, $us, $tv, $hero, $wa, $customer] = $this->enableTextVerified();

        // Quote: $0.75 mock cost → 450 XAF (rate 600) + 1000 XAF markup = 1450.
        $quote = $this->actingAs($customer)
            ->getJson(route('orders.quote', ['service_id' => $fb->id, 'country_id' => $us->id]))
            ->assertOk();
        $this->assertEquals(1450, $quote->json('price'));

        $balBefore = $customer->wallet->balance;
        $this->actingAs($customer)
            ->post(route('orders.store'), ['service_id' => $fb->id, 'country_id' => $us->id])
            ->assertRedirect();

        $order = Order::where('service_id', $fb->id)->latest()->first();
        $this->assertEquals('waiting_for_sms', $order->status);
        $this->assertStringStartsWith('TV-', $order->provider_activation_id);
        $this->assertStringStartsWith('+1212', $order->phone_number);
        $this->assertEquals(1450, $order->selling_price);
        $this->assertEquals(0.75, (float) $order->purchase_price); // USD provider cost — never shown to customers
        $this->assertEquals(1000, $order->profit);
        $this->assertEquals('textverified', $order->provider->slug);
        $this->assertEquals($balBefore - 1450, $customer->wallet->fresh()->balance);
        $this->assertEquals('purchase',
            WalletTransaction::where('reference', $order->order_id)->first()->type);
    }

    public function test_customer_cancel_releases_cost_and_refunds_as_refund(): void
    {
        [$fb, $us, $tv, $hero, $wa, $customer] = $this->enableTextVerified();
        $balBefore = $customer->wallet->balance;

        $this->actingAs($customer)
            ->post(route('orders.store'), ['service_id' => $fb->id, 'country_id' => $us->id]);
        $order = Order::latest()->first();

        $this->actingAs($customer)->delete(route('orders.cancel', $order))->assertRedirect();
        $order->refresh();

        $this->assertEquals('refunded', $order->status);
        $this->assertEquals('released', $order->provider_refund_status);
        $this->assertEquals(1450, $order->refund_amount);
        $this->assertEquals($balBefore, $customer->wallet->fresh()->balance);
        $this->assertEquals('refund',
            WalletTransaction::where('reference', $order->order_id)->latest('id')->first()->type);
        $this->assertEquals(1, \App\Models\Refund::where('order_id', $order->id)->count());
    }

    public function test_sms_delivery_transitions_to_completed_and_blocks_cancel(): void
    {
        [$fb, $us, $tv, $hero, $wa, $customer] = $this->enableTextVerified();
        $this->actingAs($customer)
            ->post(route('orders.store'), ['service_id' => $fb->id, 'country_id' => $us->id]);
        $order = Order::latest()->first();
        $id = $order->provider_activation_id;

        $v = Cache::get("tvmock:ver:{$id}");
        $v['state'] = 'VERIFICATION_COMPLETED';
        Cache::put("tvmock:ver:{$id}", $v, 3600);
        Cache::put("tvmock:deliver:{$id}", '483920', 3600);

        CheckSmsJob::dispatchSync($order);
        $order->refresh();

        $this->assertEquals('completed', $order->status);
        $this->assertEquals('483920', $order->smsMessages->first()->otp_code);

        // Terminal — cancel is refused both locally and provider-side.
        $this->actingAs($customer)->delete(route('orders.cancel', $order));
        $this->assertEquals('completed', $order->fresh()->status);
        $this->assertEquals(0, \App\Models\Refund::where('order_id', $order->id)->count());
    }

    public function test_whatsapp_still_uses_herosms(): void
    {
        [$fb, $us, $tv, $hero, $wa, $customer] = $this->enableTextVerified();

        $this->actingAs($customer)
            ->post(route('orders.store'), ['service_id' => $wa->id, 'country_id' => $us->id]);

        $order = Order::latest()->first();
        $this->assertEquals('herosms', $order->provider->slug);
        $this->assertStringStartsWith('ACT-', $order->provider_activation_id);
        $this->assertEquals('waiting_for_sms', $order->status);
    }

    public function test_expiry_on_pending_verification_releases_cost_and_refunds_once(): void
    {
        [$fb, $us, $tv, $hero, $wa, $customer] = $this->enableTextVerified();
        $balBefore = $customer->wallet->balance;

        $this->actingAs($customer)
            ->post(route('orders.store'), ['service_id' => $fb->id, 'country_id' => $us->id]);
        $order = Order::latest()->first();
        $order->update(['expires_at' => now()->subMinute()]);

        ExpireOrderJob::dispatchSync($order);
        $order->refresh();

        $this->assertEquals('expired', $order->status);
        $this->assertEquals('released', $order->provider_refund_status);
        $this->assertEquals($balBefore, $customer->wallet->fresh()->balance);

        // Idempotent — a second expiry attempt cannot refund twice.
        ExpireOrderJob::dispatchSync($order);
        $this->assertEquals(1, \App\Models\Refund::where('order_id', $order->id)->count());
        $this->assertEquals(1, WalletTransaction::where('reference', $order->order_id)
            ->where('type', 'refund')->count());
    }

    public function test_provider_timed_out_state_resolves_as_provider_refunded(): void
    {
        [$fb, $us, $tv, $hero, $wa, $customer] = $this->enableTextVerified();
        $this->actingAs($customer)
            ->post(route('orders.store'), ['service_id' => $fb->id, 'country_id' => $us->id]);
        $order = Order::latest()->first();
        $order->update(['expires_at' => now()->subMinute()]);

        $v = Cache::get("tvmock:ver:{$order->provider_activation_id}");
        $v['state'] = 'VERIFICATION_TIMED_OUT';
        Cache::put("tvmock:ver:{$order->provider_activation_id}", $v, 3600);

        ExpireOrderJob::dispatchSync($order);
        $order->refresh();

        // TextVerified auto-refunds un-coded verifications → provider_resolved.
        $this->assertEquals('expired', $order->status);
        $this->assertEquals('provider_resolved', $order->provider_refund_status);
        $this->assertNotNull($order->refund_amount);
    }

    public function test_sms_lookup_uses_the_number_verbatim_from_the_api(): void
    {
        $this->enableTextVerified();
        Http::fake(function ($req) {
            if (str_contains($req->url(), '/auth')) {
                return Http::response(['token' => 't', 'expiresAt' => now()->addHour()->toIso8601String()]);
            }
            if (str_contains($req->url(), '/sms')) {
                // The `to` filter must equal the stored number — a
                // normalized +1 form would return an empty list.
                return str_contains($req->url(), 'to=2025550123')
                    ? Http::response(['data' => [[
                        'id' => 'sms1', 'from_value' => 'Facebook',
                        'sms_content' => '483920 is your code',
                        'created_at' => now()->toIso8601String(),
                    ]]])
                    : Http::response(['data' => []]);
            }
            return Http::response([
                'id' => 'ver_1', 'number' => '2025550123', // national format
                'state' => 'VERIFICATION_COMPLETED',
            ]);
        });

        $sms = $this->liveTv()->getSms('ver_1');
        $this->assertCount(1, $sms);
        $this->assertStringContainsString('483920', $sms[0]['message']);
        $this->assertEquals('Facebook', $sms[0]['sender']);
    }

    public function test_ambiguous_create_failure_adopts_orphan_instead_of_repurchasing(): void
    {
        $this->enableTextVerified();
        $posts = 0;

        Http::fake(function ($req) use (&$posts) {
            if (str_contains($req->url(), '/auth')) {
                return Http::response(['token' => 't', 'expiresAt' => now()->addHour()->toIso8601String()]);
            }
            if ($req->method() === 'POST' && str_contains($req->url(), '/verifications')) {
                $posts++;
                return Http::response('gateway timeout', 504); // create may have succeeded server-side
            }
            if (str_ends_with($req->url(), '/verifications')) {
                // Reconcile list: an orphan pending verification exists.
                return Http::response([[
                    'id' => 'ver_orphaned', 'serviceName' => 'facebook',
                    'state' => 'VERIFICATION_PENDING',
                    'createdAt' => now()->toIso8601String(),
                ]]);
            }
            // GET /verifications/ver_orphaned — full payload
            return Http::response([
                'id' => 'ver_orphaned', 'number' => '+13125550111',
                'serviceName' => 'facebook', 'state' => 'VERIFICATION_PENDING',
                'totalCost' => 0.5, 'createdAt' => now()->toIso8601String(),
            ]);
        });

        $r = $this->liveTv()->purchaseNumber('US', 'facebook');

        // Ambiguous failure reconciled into the existing verification —
        // exactly ONE create was ever sent.
        $this->assertEquals('success', $r['status']);
        $this->assertEquals('ver_orphaned', $r['activation_id']);
        $this->assertEquals(1, $posts);
    }

    public function test_mock_orphan_hook_exercises_the_same_reconcile_path(): void
    {
        [$fb] = $this->enableTextVerified();
        config(['services.textverified' => ['enabled' => true, 'mode' => 'mock']]);
        $p = new TextVerifiedProvider();

        Cache::put('tvmock:orphan', true, 60);
        $r = $p->purchaseNumber('US', 'facebook');

        $this->assertEquals('success', $r['status']);
        $this->assertStringStartsWith('TV-', $r['activation_id']);
    }

    public function test_missing_credentials_fail_closed(): void
    {
        config(['services.textverified' => [
            'enabled' => true, 'mode' => 'production',
            'base_url' => 'https://www.textverified.com',
            'api_key' => '', 'username' => '',
        ]]);
        $p = new TextVerifiedProvider();

        $this->assertFalse($p->isActive());

        Http::fake($this->fakeAuth());
        try {
            $p->purchaseNumber('US', 'facebook');
            $this->fail('expected failure without credentials');
        } catch (TextVerifiedException $e) {
            // Auth headers were blank — nothing purchased.
            $this->assertContains($e->errorCode, ['AUTH_FAILED', 'PARSE_ERROR', 'NO_MAPPING']);
        }
    }

    public function test_non_us_country_rejected(): void
    {
        $this->expectException(TextVerifiedException::class);
        $this->expectExceptionMessage('US-only');
        $this->liveTv()->purchaseNumber('GB', 'facebook');
    }

    public function test_service_without_mapping_rejected_live(): void
    {
        $this->enableTextVerified();
        Http::fake($this->fakeAuth());

        $this->expectException(TextVerifiedException::class);
        $this->liveTv()->purchaseNumber('US', 'whatsapp'); // NO_MAPPING
    }

    public function test_availability_only_for_facebook_us(): void
    {
        $this->enableTextVerified();
        Http::fake(array_merge($this->fakeAuth(), [
            '*/api/pub/v2/pricing/verifications' => Http::response(['price' => 0.9]),
            '*/api/pub/v2/inventory/verifications' => Http::response(['quantity' => 5]),
        ]));

        $p = $this->liveTv();
        $this->assertNotEmpty($p->getAvailableNumbers('US', 'facebook'));
        $this->assertEmpty($p->getAvailableNumbers('GB', 'facebook'));
        $this->assertEmpty($p->getAvailableNumbers('US', 'whatsapp'));
    }

    public function test_credentials_and_tokens_never_logged(): void
    {
        $this->enableTextVerified();
        Http::fake(array_merge($this->fakeAuth('super-secret-bearer'), [
            '*/api/pub/v2/verifications' => Http::response([
                'id' => 'v1', 'number' => '+12025550100',
                'totalCost' => 0.5, 'state' => 'VERIFICATION_PENDING',
            ]),
        ]));

        $this->liveTv()->purchaseNumber('US', 'facebook');

        foreach (ProviderLog::all() as $log) {
            $blob = json_encode($log->getAttributes());
            $this->assertStringNotContainsString('tv-secret-key', $blob);
            $this->assertStringNotContainsString('super-secret-bearer', $blob);
            $this->assertStringNotContainsString('ops@example.com', $blob);
        }
        $this->assertGreaterThan(0, ProviderLog::count());
    }

    public function test_database_seeder_registers_textverified_routing(): void
    {
        $this->seedMarketplace();
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder'])->assertSuccessful();

        $tv = Provider::where('slug', 'textverified')->first();
        $this->assertNotNull($tv);

        $fb = Service::where('slug', 'facebook')->first();
        $this->assertEquals('textverified', $fb->provider_mapping['provider']);
        $this->assertEquals(['US'], $fb->provider_mapping['countries']);
        $this->assertTrue(ProviderService::where('provider_id', $tv->id)
            ->where('service_id', $fb->id)->exists());
    }

    public function test_temporarily_unavailable_flag_still_blocks_facebook(): void
    {
        [$fb, $us, $tv, $hero, $wa, $customer] = $this->enableTextVerified();
        $fb->update(['temporarily_unavailable' => true]);

        $this->actingAs($customer)
            ->post(route('orders.store'), ['service_id' => $fb->id, 'country_id' => $us->id]);

        $this->assertEquals(0, Order::where('service_id', $fb->id)->count());
        $this->assertEquals($customer->wallet->balance, $customer->wallet->fresh()->balance);
    }
}
