<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

/**
 * Smartsupp live chat: the official loader is rendered once through a
 * shared component on every customer-facing layout — landing, auth,
 * public pages, and the logged-in app — and never on /admin/*.
 */
class SmartsuppTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    protected function assertChatPresent(string $html): void
    {
        $this->assertStringContainsString('_smartsupp.key', $html);
        $this->assertStringContainsString('smartsuppchat.com/loader.js', $html);
    }

    public function test_landing_page_loads_chat(): void
    {
        $this->seedMarketplace();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertChatPresent($html);
        // single include — no duplication
        $this->assertSame(1, substr_count($html, 'smartsuppchat.com/loader.js'));
    }

    public function test_logged_in_dashboard_loads_chat_once(): void
    {
        $this->seedMarketplace();
        $html = $this->actingAs($this->customer())
            ->get('/dashboard')->assertOk()->getContent();
        $this->assertChatPresent($html);
        $this->assertSame(1, substr_count($html, 'smartsuppchat.com/loader.js'));
    }

    public function test_logged_out_auth_page_loads_chat(): void
    {
        $this->seedMarketplace();
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertChatPresent($html);
    }

    public function test_admin_pages_have_no_chat(): void
    {
        $this->seedMarketplace();
        $admin = $this->admin();

        foreach (['/admin/login', '/admin/dashboard', '/admin/promotions'] as $path) {
            $html = str_contains($path, 'login')
                ? $this->get($path)->assertOk()->getContent()
                : $this->actingAs($admin, 'admin')->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString('smartsuppchat.com', $html, "Chat leaked onto {$path}");
            $this->assertStringNotContainsString('_smartsupp', $html, "Chat key leaked onto {$path}");
        }
    }

    public function test_admin_login_has_no_chat_for_guest(): void
    {
        $this->seedMarketplace();
        $html = $this->get('/admin/login')->assertOk()->getContent();
        $this->assertStringNotContainsString('smartsuppchat.com', $html);
    }
}
