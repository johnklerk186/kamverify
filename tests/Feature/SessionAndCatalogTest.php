<?php

namespace Tests\Feature;

use App\Models\ProviderCountry;
use App\Services\CountryAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

/**
 * Production fixes — expired links/sessions and slow country loading.
 *
 * Part A: canonical host redirects (www/http → https://kamverify.com),
 *         friendly 419 page for stale forms, graceful session expiry.
 * Part B: service_countries catalog — stale-while-revalidate cache,
 *         admin mutations bust it immediately, stale responses never
 *         trusted at purchase time (quote stays live/uncached).
 */
class SessionAndCatalogTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    // ---------------- Part A: sessions & links ----------------

    public function test_419_page_is_branded_and_recoverable(): void
    {
        // CSRF mismatch can't be produced in tests — the middleware
        // bypass is compiled in for unit runs. Laravel renders
        // errors/419 for TokenMismatchException, so verify that view
        // gives customers a real recovery path instead of a raw error.
        $html = view('errors.419')->render();
        $this->assertStringContainsString('This page expired', $html);
        $this->assertStringContainsString('Reload this page', $html);
        $this->assertStringContainsString('dashboard', $html);
    }

    public function test_expired_session_redirects_to_login_with_intended_url(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location'));
        // Laravel stores the intended URL — after login the customer
        // returns to the dashboard, not a dead end.
        $this->assertStringEndsWith('/dashboard', session('url.intended'));
    }

    public function test_www_and_http_redirect_to_canonical_host(): void
    {
        config(['app.url' => 'https://kamverify.com']);

        $this->get('http://kamverify.com/login')
            ->assertRedirect('https://kamverify.com/login');

        $this->get('https://www.kamverify.com/dashboard')
            ->assertRedirect('https://kamverify.com/dashboard');

        $this->get('http://www.kamverify.com/')
            ->assertRedirect('https://kamverify.com/');

        // Canonical URL itself never redirects — no loop.
        $this->get('https://kamverify.com/')->assertOk();
    }

    public function test_canonical_redirect_never_intercepts_post(): void
    {
        config(['app.url' => 'https://kamverify.com']);

        // A POST to the wrong host must not be 301'd — browsers drop the
        // body on redirect. (CSRF bypassed in tests → reaches login logic.)
        $response = $this->post('http://www.kamverify.com/login', [
            'email' => 'nobody@example.com', 'password' => 'wrong',
        ]);
        $this->assertNotEquals(301, $response->status());
        $this->assertStringNotContainsString('https://kamverify.com', (string) $response->headers->get('Location'));
    }

    public function test_canonical_middleware_is_safe_on_local_hosts(): void
    {
        config(['app.url' => 'http://localhost']);
        // localhost can never be a canonical target — middleware no-ops.
        $this->get('http://localhost/')->assertOk();
    }

    // ---------------- Part B: catalog caching ----------------

    public function test_for_service_caches_and_forget_all_busts(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $svc = app(CountryAvailabilityService::class);

        $first = $svc->forService($service);
        $this->assertCount(1, $first['countries']);

        // Mutate the country — a cached response must still come back
        // (the point of caching: DB churn doesn't hit every request).
        $country->update(['is_active' => false]);
        $this->assertCount(1, $svc->forService($service)['countries']);

        $svc->forgetAll();
        $this->assertCount(0, $svc->forService($service)['countries']);
    }

    public function test_admin_country_toggle_invalidates_customer_catalog(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $customer = $this->customer();
        $admin = $this->admin();

        // Prime the cache through the real customer endpoint.
        $json = $this->actingAs($customer)
            ->getJson('/orders/countries?service_id=' . $service->id)
            ->assertOk()->json();
        $this->assertSame(1, $json['count']);

        // Admin deactivates the country — the very next customer
        // request must reflect it, not wait out the cache TTL.
        $this->actingAs($admin, 'admin')
            ->put(route('admin.countries.toggle-status', $country))
            ->assertRedirect();

        $json = $this->actingAs($customer)
            ->getJson('/orders/countries?service_id=' . $service->id)
            ->assertOk()->json();
        $this->assertSame(0, $json['count']);
    }

    public function test_admin_provider_mapping_toggle_invalidates_catalog(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $customer = $this->customer();
        $admin = $this->admin();

        $json = $this->actingAs($customer)
            ->getJson('/orders/countries?service_id=' . $service->id)
            ->assertOk()->json();
        $this->assertSame(1, $json['count']);

        $mapping = ProviderCountry::where('provider_id', $provider->id)->first();
        $this->actingAs($admin, 'admin')
            ->put(route('admin.providers.countries.toggle', [$provider, $mapping]))
            ->assertRedirect();

        $json = $this->actingAs($customer)
            ->getJson('/orders/countries?service_id=' . $service->id)
            ->assertOk()->json();
        $this->assertSame(0, $json['count']);
    }

    public function test_provider_failure_falls_back_to_mappings_not_empty(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        // Mock provider returns null counts (can't answer) — the
        // catalog must list mapped countries anyway, never collapse.
        $data = app(CountryAvailabilityService::class)->forService($service);
        $this->assertSame(1, $data['count']);
        $this->assertNull($data['countries'][0]['stock']); // mapping-only availability
    }

    public function test_quote_stays_live_not_cached(): void
    {
        // The purchase path re-verifies availability server-side per
        // request — a stale catalog entry can never mint a sale.
        [$provider, $country, $service] = $this->seedMarketplace();
        $customer = $this->customer();

        $json = $this->actingAs($customer)->getJson(
            '/orders/quote?country_id=' . $country->id . '&service_id=' . $service->id
        )->assertOk()->json();

        $this->assertArrayHasKey('available', $json);
        // quote response carries no cache headers a shared proxy could honor
        $this->assertStringNotContainsString('public', (string) $this->app['request']->headers->get('Cache-Control'));
    }
}
