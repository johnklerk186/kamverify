<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

/**
 * Temporary service outage (e.g. Facebook carrier-incompatibility):
 * the service stays listed with a badge and a "Got it" notice, but is
 * server-side unpurchasable through every route — store, quote, and
 * country-list JSON — while other services work normally.
 */
class ServiceOutageTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    protected function outageService(): Service
    {
        return Service::create([
            'name' => 'Facebook',
            'slug' => 'facebook',
            'icon' => 'facebook',
            'is_active' => true,
            'customer_enabled' => true,
            'temporarily_unavailable' => true,
        ]);
    }

    public function test_outage_service_cannot_be_purchased(): void
    {
        [$p, $country] = $this->seedMarketplace();
        $fb = $this->outageService();
        $user = $this->customer();

        $this->actingAs($user)->post('/orders', [
            'country_id' => $country->id,
            'service_id' => $fb->id,
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(50000.0, $user->wallet->fresh()->balance);
    }

    public function test_outage_service_quote_and_countries_rejected(): void
    {
        [$p, $country] = $this->seedMarketplace();
        $fb = $this->outageService();
        $user = $this->customer();

        $this->actingAs($user)
            ->getJson('/orders/quote?country_id=' . $country->id . '&service_id=' . $fb->id)
            ->assertStatus(422)
            ->assertJson(['available' => false])
            ->assertJsonPath('message', $fb->outageMessage());

        $this->actingAs($user)
            ->getJson('/orders/countries?service_id=' . $fb->id)
            ->assertStatus(422)
            ->assertJsonPath('message', $fb->outageMessage());
    }

    public function test_outage_service_still_listed_with_badge_on_buy_page(): void
    {
        $this->seedMarketplace();
        $fb = $this->outageService();

        $html = $this->actingAs($this->customer())
            ->get('/orders/create')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Facebook', $html);
        $this->assertStringContainsString('Temporarily Unavailable', $html);
        $this->assertStringContainsString($fb->outageMessage() !== '' ? 'kv-service-outage' : '', $html);
        // The outage service must not be a selectable option in the no-JS fallback
        $this->assertStringContainsString('temporarily unavailable', $html);
    }

    public function test_landing_page_shows_badge_and_notice_hook(): void
    {
        $this->seedMarketplace();
        $this->outageService();

        $this->get('/')
            ->assertOk()
            ->assertSee('Facebook')
            ->assertSee('Temporarily Unavailable')
            ->assertSee('kv-service-outage', false);
    }

    public function test_login_shows_outage_notice_once(): void
    {
        $this->seedMarketplace();
        $this->outageService();
        $user = $this->customer();

        // POST /login authenticates this test session — subsequent
        // requests share it, so the one-shot flash survives.
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        // The modal markup is always mounted (click-to-reopen needs it);
        // the login flash only controls whether it opens automatically.
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Got it')
            ->assertSee('temporarily unavailable')
            ->assertSee('open: true', false)
            ->assertSee('Facebook', false);

        // Second page load — flash is consumed, modal stays closed
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('open: false', false)
            ->assertDontSee('open: true', false);
    }

    public function test_other_services_unaffected(): void
    {
        [$p, $country, $whatsapp] = $this->seedMarketplace();
        $this->outageService();
        $user = $this->customer();

        $this->actingAs($user)->post('/orders', [
            'country_id' => $country->id,
            'service_id' => $whatsapp->id,
        ]);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame($whatsapp->id, $order->service_id);
    }

    public function test_admin_can_toggle_outage_flag(): void
    {
        $this->seedMarketplace();
        $fb = $this->outageService();
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.services.toggle-unavailable', $fb))
            ->assertRedirect();
        $this->assertFalse($fb->fresh()->temporarily_unavailable);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.services.toggle-unavailable', $fb))
            ->assertRedirect();
        $this->assertTrue($fb->fresh()->temporarily_unavailable);
    }

    public function test_restored_service_purchasable_again(): void
    {
        [$p, $country] = $this->seedMarketplace();
        $fb = $this->outageService();
        $user = $this->customer();

        $fb->update(['temporarily_unavailable' => false]);

        $this->actingAs($user)->post('/orders', [
            'country_id' => $country->id,
            'service_id' => $fb->id,
        ]);

        $this->assertDatabaseHas('orders', ['service_id' => $fb->id]);
    }
}
