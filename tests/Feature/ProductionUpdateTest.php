<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\KamVerifyNotification;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

class ProductionUpdateTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    // ---------- Services visibility ----------

    public function test_only_customer_enabled_services_appear_on_buy_page(): void
    {
        $this->seedMarketplace(); // whatsapp, customer_enabled
        Service::create(['name' => 'Netflix', 'slug' => 'netflix', 'is_active' => true, 'customer_enabled' => false]);

        $this->actingAs($this->customer())
            ->get('/orders/create')
            ->assertOk()
            ->assertSee('WhatsApp')
            ->assertDontSee('Netflix');
    }

    public function test_disabled_service_cannot_be_purchased(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $service->update(['customer_enabled' => false]);

        $this->actingAs($this->customer())
            ->post('/orders', ['service_id' => $service->id, 'country_id' => $country->id]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_quote_rejects_non_customer_enabled_service(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $service->update(['customer_enabled' => false]);

        $this->actingAs($this->customer())
            ->getJson("/orders/quote?country_id={$country->id}&service_id={$service->id}")
            ->assertStatus(422);
    }

    // ---------- Minimum deposit (100 XAF) ----------

    public function test_deposit_below_100_xaf_is_rejected(): void
    {
        $user = $this->customer();

        foreach ([50, 99] as $amount) {
            $this->actingAs($user)
                ->post('/wallet/deposit', ['amount' => $amount, 'payment_method' => 'mock'])
                ->assertSessionHasErrors('amount');
        }

        $this->assertEquals(50000, (float) $user->wallet->fresh()->balance);
    }

    public function test_deposit_of_100_xaf_is_accepted(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->post('/wallet/deposit', ['amount' => 100, 'payment_method' => 'mock']);

        $this->assertEquals(50100, (float) $user->wallet->fresh()->balance);
    }

    public function test_decimal_amount_is_rejected(): void
    {
        $this->actingAs($this->customer())
            ->post('/wallet/deposit', ['amount' => '100.50', 'payment_method' => 'mock'])
            ->assertSessionHasErrors('amount');
    }

    public function test_invalid_phone_rejected_in_live_mode(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->post('/wallet/deposit', [
                'amount' => 1000,
                'payment_method' => 'mtn_momo',
                'phone' => '123',
            ])
            ->assertSessionHasErrors('phone');
    }

    // ---------- Notifications on real events ----------

    public function test_deposit_events_generate_notifications(): void
    {
        Notification::fake();
        $user = $this->customer();
        $payments = app(PaymentService::class);

        $payment = $payments->createPayment($user, 1000, 'mock');
        Notification::assertSentTo($user, KamVerifyNotification::class,
            fn ($n) => $n->notificationType === 'deposit_initiated');

        $payments->processSuccessfulPayment($payment);
        Notification::assertSentTo($user, \App\Notifications\DepositSuccessful::class);
    }

    public function test_failed_payment_generates_notification(): void
    {
        Notification::fake();
        $user = $this->customer();

        $payment = app(PaymentService::class)->createPayment($user, 1000, 'mock');
        app(PaymentService::class)->processFailedPayment($payment, 'user declined');

        Notification::assertSentTo($user, KamVerifyNotification::class,
            fn ($n) => $n->notificationType === 'deposit_failed');
    }

    public function test_order_lifecycle_generates_notifications(): void
    {
        Notification::fake();
        config(['services.herosms.mode' => 'mock']);
        [$provider, $country, $service] = $this->seedMarketplace();

        $user = $this->customer();
        $this->actingAs($user)->post('/orders', [
            'service_id' => $service->id,
            'country_id' => $country->id,
        ]);

        Notification::assertSentTo($user, \App\Notifications\OrderCreated::class);
        Notification::assertSentTo($user, KamVerifyNotification::class,
            fn ($n) => $n->notificationType === 'number_assigned');
    }

    public function test_admin_notification_send_to_all_customers(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customers = User::factory()->count(3)->create(['role' => 'customer']);

        $this->actingAs($admin, 'admin')
            ->post('/admin/notifications/send', [
                'audience' => 'all',
                'title' => 'Scheduled Maintenance',
                'message' => 'KamVerify will undergo maintenance tonight.',
                'type' => 'maintenance',
            ])
            ->assertRedirect();

        foreach ($customers as $c) {
            Notification::assertSentTo($c, KamVerifyNotification::class,
                fn ($n) => $n->title === 'Scheduled Maintenance');
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'notification.send']);
    }

    public function test_customer_cannot_access_admin_notification_send(): void
    {
        $this->actingAs($this->customer())
            ->post('/admin/notifications/send', [
                'audience' => 'all',
                'title' => 'X',
                'message' => 'Y',
                'type' => 'announcement',
            ]);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'notification.send']);
    }

    // ---------- Fapshi protocol (mocked HTTP) ----------

    public function test_fapshi_direct_pay_sends_documented_parameters(): void
    {
        config(['services.fapshi.mode' => 'sandbox']);
        config(['services.fapshi.use_mock' => false]);
        config(['services.fapshi.api_user' => 'test-user', 'services.fapshi.api_key' => 'test-key']);

        Http::fake([
            'sandbox.fapshi.com/direct-pay' => Http::response([
                'transId' => 'abc123',
                'message' => 'Payment request sent',
                'dateInitiated' => '2024-09-21',
            ]),
        ]);

        $provider = new \App\Services\Payments\FapshiPaymentProvider();
        $result = $provider->createPayment(5000, 'XAF', [
            'phone' => '237670000000',
            'name' => 'Test User',
            'email' => 'c@example.com',
            'user_id' => 7,
            'external_id' => 'KV-PAY-TEST',
        ]);

        $this->assertSame('abc123', $result['payment_id']);
        $this->assertNull($result['redirect_url']); // no hosted redirect
        $this->assertTrue($result['direct_pay']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox.fapshi.com/direct-pay'
                && $request->hasHeader('apiuser', 'test-user')
                && $request->hasHeader('apikey', 'test-key')
                && $request['amount'] === 5000
                && $request['phone'] === '670000000' // 237 prefix stripped
                && $request['medium'] === 'mtn'
                && $request['externalId'] === 'KV-PAY-TEST'
                && $request['userId'] === '7'
                && !isset($request['redirectUrl']); // direct pay never sends it
        });
    }

    public function test_fapshi_initiate_pay_still_used_without_phone(): void
    {
        config(['services.fapshi.mode' => 'sandbox']);
        config(['services.fapshi.use_mock' => false]);
        config(['services.fapshi.api_user' => 'test-user', 'services.fapshi.api_key' => 'test-key']);

        Http::fake([
            'sandbox.fapshi.com/initiate-pay' => Http::response([
                'link' => 'https://checkout.fapshi.com/pay/abc123',
                'transId' => 'abc123',
            ]),
        ]);

        $result = (new \App\Services\Payments\FapshiPaymentProvider())
            ->createPayment(5000, 'XAF', ['user_id' => 7]);

        $this->assertSame('abc123', $result['payment_id']);
        $this->assertSame('https://checkout.fapshi.com/pay/abc123', $result['redirect_url']);
        $this->assertFalse($result['direct_pay']);
    }

    public function test_live_deposit_requires_phone(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        config(['services.fapshi.use_mock' => false]);

        $this->actingAs($this->customer())->post('/wallet/deposit', [
            'amount' => 5000,
            'payment_method' => 'mtn_momo',
        ])->assertSessionHasErrors('phone');
    }

    public function test_fapshi_status_mapping(): void
    {
        config(['services.fapshi.mode' => 'live']);
        
        
        
        config(['services.fapshi.use_mock' => false]);
        config(['services.fapshi.api_user' => 'u', 'services.fapshi.api_key' => 'k']);

        Http::fake([
            'live.fapshi.com/payment-status/*' => Http::response([
                'transId' => 't1', 'status' => 'SUCCESSFUL', 'amount' => 5000,
            ]),
        ]);

        $status = (new \App\Services\Payments\FapshiPaymentProvider())->getPaymentStatus('t1');
        $this->assertSame('successful', $status['status']);
    }

    public function test_fapshi_missing_credentials_fail_safely(): void
    {
        
        config(['services.fapshi.use_mock' => false]);
        config(['services.fapshi.api_user' => '', 'services.fapshi.api_key' => '']);

        $this->expectException(\RuntimeException::class);
        (new \App\Services\Payments\FapshiPaymentProvider())->createPayment(1000, 'XAF', ['phone' => '670000000']);
    }

    // ---------- Facebook VPN notice ----------

    public function test_facebook_purchase_works_without_extra_gate(): void
    {
        config(['services.herosms.mode' => 'mock']);
        [$provider, $country, $service] = $this->seedMarketplace();
        $service->update(['slug' => 'facebook']);

        // The instruction modal is the sole notice now — no second
        // checkbox gate blocks the purchase itself.
        $user = $this->customer();
        $this->actingAs($user)->post('/orders', [
            'service_id' => $service->id,
            'country_id' => $country->id,
        ]);

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_old_vpn_acknowledgment_panel_removed(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        Service::create(['name' => 'Facebook', 'slug' => 'facebook', 'is_active' => true, 'customer_enabled' => true]);

        $this->actingAs($this->customer())
            ->get('/orders/create')
            ->assertOk()
            ->assertDontSee('Important — Facebook Verification', false)
            ->assertDontSee('vpn_acknowledged', false)
            ->assertDontSee('vpnAck', false);
    }

    public function test_service_instruction_modal_configured_for_facebook_and_telegram_only(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        Service::create(['name' => 'Facebook', 'slug' => 'facebook', 'is_active' => true, 'customer_enabled' => true]);
        Service::create(['name' => 'Telegram', 'slug' => 'telegram', 'is_active' => true, 'customer_enabled' => true]);
        Service::create(['name' => 'TikTok', 'slug' => 'tiktok', 'is_active' => true, 'customer_enabled' => true]);

        $html = $this->actingAs($this->customer())
            ->get('/orders/create')
            ->assertOk()
            ->getContent();

        // Notice config present for facebook + telegram with exact copy
        $this->assertStringContainsString('Instructions for Facebook / Meta Viewpoints', $html);
        $this->assertStringContainsString('Instructions for Telegram', $html);
        $this->assertStringContainsString('facebook:', $html);
        $this->assertStringContainsString('telegram:', $html);
        $this->assertStringContainsString("Don't show again for this service", $html);
        $this->assertStringContainsString('OK, I Understand', $html);
        $this->assertStringContainsString('kv_notice_off_', $html);

        // WhatsApp (seeded service) and TikTok have no notice entries —
        // they must not appear as keys inside serviceNotices
        $this->assertStringNotContainsString('whatsapp:', $html);
        $this->assertStringNotContainsString('tiktok:', $html);
    }

    // ---------- Currency ----------

    public function test_customer_pages_display_xaf_not_dollars(): void
    {
        $this->seedMarketplace();
        $user = $this->customer();

        foreach (['/dashboard', '/wallet', '/wallet/deposit', '/orders/create', '/orders'] as $page) {
            $html = $this->actingAs($user)->get($page)->assertOk()->getContent();
            $this->assertStringNotContainsString('USD', $html, "$page still shows USD");
            $this->assertMatchesRegularExpression('/XAF/', $html, "$page shows no XAF");
        }
    }
}
