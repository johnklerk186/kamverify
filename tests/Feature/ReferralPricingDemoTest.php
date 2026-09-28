<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\Setting;
use App\Services\DemoDataSeeder;
use App\Services\PricingService;
use App\Services\ReferralService;
use App\Support\DemoAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

class ReferralPricingDemoTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    public function test_registration_alone_does_not_pay_referral_reward(): void
    {
        $referrer = $this->customer();
        $referrer->generateReferralCode();

        $this->post('/register', [
            'name' => 'Referred User',
            'email' => 'referred@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'referral_code' => $referrer->referral_code,
        ]);

        $this->assertSame(1, Referral::count());
        $this->assertSame(0, ReferralReward::count());
        $this->assertEquals(50000, (float) $referrer->wallet->fresh()->balance);
    }

    public function test_reward_paid_on_first_successful_purchase_only(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        Setting::set('referral_reward_type', 'percentage', 'string', 'referral');
        Setting::set('referral_reward_value', '10', 'integer', 'referral');

        $referrer = $this->customer();
        $referrer->generateReferralCode();
        $referred = $this->customer();

        app(ReferralService::class)->createReferral($referrer, $referred, $referrer->referral_code);

        // Order created but number never delivered (provider failure) → no reward
        $order = app(\App\Services\OrderService::class)
            ->createOrder($referred, $country, $service, $provider, 0.50);
        $this->assertSame(0, ReferralReward::count());

        // Number assigned = successful purchase → reward paid once
        app(\App\Services\OrderService::class)->assignNumber($order, '+15551234567', 'ACT-TEST1');
        $this->assertSame(1, ReferralReward::count());
        $expected = 50000 + $order->selling_price * 0.10;
        $this->assertEquals($expected, (float) $referrer->wallet->fresh()->balance);

        // Second purchase → no additional reward
        $order2 = app(\App\Services\OrderService::class)
            ->createOrder($referred, $country, $service, $provider, 0.50);
        app(\App\Services\OrderService::class)->assignNumber($order2, '+15557654321', 'ACT-TEST2');
        $this->assertSame(1, ReferralReward::count());
    }

    public function test_self_referral_is_rejected(): void
    {
        $user = $this->customer();
        $user->generateReferralCode();

        $this->expectException(\Exception::class);
        app(ReferralService::class)->createReferral($user, $user, $user->referral_code);
    }

    public function test_pricing_percentage_and_fixed_markup(): void
    {
        // Deterministic rate: $1.00 provider cost = 600 XAF.
        Setting::set('usd_to_xaf_rate', '600', 'float', 'pricing');
        Setting::set('default_markup_type', 'percentage', 'string', 'pricing');
        Setting::set('default_markup_value', '30', 'integer', 'pricing');

        $pricing = app(PricingService::class);
        // 600 XAF × 1.30 = 780 XAF (integer, no decimals)
        $this->assertSame(780, $pricing->calculateSellingPrice(1.00));

        // Fixed markup is expressed in XAF: 600 + 100 = 700
        Setting::set('default_markup_type', 'fixed', 'string', 'pricing');
        Setting::set('default_markup_value', '100', 'float', 'pricing');
        $this->assertSame(700, $pricing->calculateSellingPrice(1.00));
    }

    public function test_demo_entry_logs_in_demo_user_and_issues_token(): void
    {
        config(['app.demo_enabled' => true]);

        $response = $this->get('/demo');
        $response->assertRedirect(route('dashboard'));

        $this->assertTrue(auth('web')->check());
        $this->assertSame(DemoDataSeeder::DEMO_EMAIL, auth('web')->user()->email);
        $response->assertCookie(DemoAccess::COOKIE);
    }

    public function test_demo_cookie_authenticates_without_session(): void
    {
        config(['app.demo_enabled' => true]);

        $demo = app(DemoDataSeeder::class)->demoUser();
        $token = DemoAccess::tokenFor($demo);

        // Replicate what a real browser sends: the response cookie is
        // Laravel-encrypted, so EncryptCookies must be able to decrypt it.
        $encrypted = encrypt(
            \Illuminate\Cookie\CookieValuePrefix::create(DemoAccess::COOKIE, app('encrypter')->getKey()) . $token,
            false
        );

        // No session — only the signed demo cookie
        $this->withUnencryptedCookie(DemoAccess::COOKIE, $encrypted)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_forged_demo_cookie_is_rejected(): void
    {
        config(['app.demo_enabled' => true]);
        app(DemoDataSeeder::class)->demoUser();

        $this->withUnencryptedCookie(DemoAccess::COOKIE, 'forged-token')
            ->get('/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_demo_disabled_blocks_entry(): void
    {
        config(['app.demo_enabled' => false]);

        $response = $this->get('/demo');
        $this->assertNotSame(200, $response->status());
        $this->assertFalse(auth('web')->check());
    }
}
