<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    public function test_customer_cannot_access_admin_pages(): void
    {
        $customer = $this->customer();

        $response = $this->actingAs($customer)->get('/admin/dashboard');
        $this->assertContains($response->status(), [302, 403]);
        $this->assertNotSame(200, $response->status());
    }

    public function test_admin_can_login_via_admin_portal(): void
    {
        $admin = $this->admin();

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(auth('admin')->check());
    }

    public function test_customer_credentials_rejected_at_admin_login(): void
    {
        $customer = $this->customer();

        $this->post('/admin/login', [
            'email' => $customer->email,
            'password' => 'password',
        ]);

        $this->assertFalse(auth('admin')->check());
    }

    public function test_admin_account_rejected_at_customer_login(): void
    {
        $admin = $this->admin();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.login'));
        $this->assertFalse(auth('web')->check());
    }

    public function test_admin_session_does_not_grant_customer_area(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        // Admin guard is authenticated but web guard is not — customer
        // routes must not treat an admin session as a customer login.
        $response = $this->get('/dashboard');
        $this->assertNotSame(200, $response->status());
    }

    public function test_analytics_requires_admin(): void
    {
        $this->get('/admin/analytics')->assertRedirect(route('admin.login'));

        $customer = $this->customer();
        $response = $this->actingAs($customer)->get('/admin/analytics');
        $this->assertNotSame(200, $response->status());
    }
}
