<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

/**
 * WhatsApp group button: rendered once through a shared component on
 * every customer-facing layout — landing, auth, public pages, and the
 * logged-in app — and never on /admin/*. Replaces the old Smartsupp
 * live-chat loader.
 */
class WhatsAppChatTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    protected const GROUP_URL = 'https://chat.whatsapp.com/C8nH3ePfWV0AORciaxTuMD';

    protected function assertChatPresent(string $html): void
    {
        $this->assertStringContainsString(self::GROUP_URL, $html);
        $this->assertStringContainsString('aria-label="Join our WhatsApp group"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_landing_page_has_whatsapp_button(): void
    {
        $this->seedMarketplace();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertChatPresent($html);
        $this->assertSame(1, substr_count($html, 'chat.whatsapp.com'));
        // old chat provider is gone
        $this->assertStringNotContainsString('smartsupp', $html);
    }

    public function test_logged_in_dashboard_has_whatsapp_button_once(): void
    {
        $this->seedMarketplace();
        $html = $this->actingAs($this->customer())
            ->get('/dashboard')->assertOk()->getContent();
        $this->assertChatPresent($html);
        $this->assertSame(1, substr_count($html, 'chat.whatsapp.com'));
    }

    public function test_logged_out_auth_page_has_whatsapp_button(): void
    {
        $this->seedMarketplace();
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertChatPresent($html);
    }

    public function test_admin_pages_have_no_whatsapp_button(): void
    {
        $this->seedMarketplace();
        $admin = $this->admin();

        foreach (['/admin/login', '/admin/dashboard', '/admin/promotions'] as $path) {
            $html = str_contains($path, 'login')
                ? $this->get($path)->assertOk()->getContent()
                : $this->actingAs($admin, 'admin')->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString('chat.whatsapp.com', $html, "WhatsApp button leaked onto {$path}");
            $this->assertStringNotContainsString('whatsapp-chat', $html, "WhatsApp component leaked onto {$path}");
        }
    }

    public function test_blank_group_url_hides_button(): void
    {
        config(['services.whatsapp.group_url' => '']);
        $this->seedMarketplace();
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringNotContainsString('chat.whatsapp.com', $html);
    }
}
