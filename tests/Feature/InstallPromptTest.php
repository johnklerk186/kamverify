<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

/**
 * "Add to Home Screen" prompt + PWA readiness.
 *
 * The floating prompt is rendered only inside the customer dashboard
 * view — never on admin, public, or auth pages. Timing (2s delay /
 * 30s max), dismissal persistence, standalone suppression, and the
 * native beforeinstallprompt flow all live in kvInstallPrompt() in
 * resources/js/app.js; these tests pin the server-rendered contract
 * (markup, hooks, manifest, icons) that logic depends on.
 */
class InstallPromptTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    // ---------- prompt placement ----------

    public function test_dashboard_renders_install_prompt(): void
    {
        $this->seedMarketplace();
        $html = $this->actingAs($this->customer())->get('/dashboard')
            ->assertOk()->getContent();

        $this->assertStringContainsString('kvInstallPrompt()', $html);
        $this->assertStringContainsString('Install KamVerify', $html);
        $this->assertStringContainsString('Add KamVerify to your home screen', $html);
        $this->assertStringContainsString('Add to Home Screen', $html);
        // exactly one prompt, no duplicates
        $this->assertSame(1, substr_count($html, 'kvInstallPrompt()'));
    }

    public function test_prompt_not_on_public_pages(): void
    {
        $this->seedMarketplace();
        foreach (['/', '/login', '/register', '/pages/terms', '/pages/privacy'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringNotContainsString('kvInstallPrompt', $html, "Prompt leaked onto {$path}");
            $this->assertStringNotContainsString('Install KamVerify', $html, "Prompt leaked onto {$path}");
        }
    }

    public function test_prompt_not_on_other_customer_pages(): void
    {
        $this->seedMarketplace();
        $customer = $this->customer();
        foreach (['/orders', '/wallet', '/support'] as $path) {
            $html = $this->actingAs($customer)->get($path)->getContent();
            $this->assertStringNotContainsString('kvInstallPrompt', $html, "Prompt leaked onto {$path}");
        }
    }

    public function test_prompt_not_on_admin_pages(): void
    {
        $this->seedMarketplace();
        $admin = $this->admin();
        foreach (['/admin/login', '/admin/dashboard'] as $path) {
            $html = str_contains($path, 'login')
                ? $this->get($path)->assertOk()->getContent()
                : $this->actingAs($admin, 'admin')->get($path)->assertOk()->getContent();
            $this->assertStringNotContainsString('kvInstallPrompt', $html, "Prompt leaked onto {$path}");
        }
    }

    public function test_ios_manual_instructions_present(): void
    {
        $this->seedMarketplace();
        $html = $this->actingAs($this->customer())->get('/dashboard')->getContent();
        $this->assertStringContainsString('Share', $html);
        $this->assertStringContainsString("modal === 'ios'", $html);
    }

    // ---------- app.js install logic contract ----------

    public function test_app_js_implements_install_lifecycle(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));

        // native prompt captured early + invoked on tap
        $this->assertStringContainsString('beforeinstallprompt', $js);
        $this->assertStringContainsString('p.prompt()', $js);
        $this->assertStringContainsString('p.userChoice', $js);
        // install detection + persistence
        $this->assertStringContainsString("display-mode: standalone", $js);
        $this->assertStringContainsString('navigator.standalone', $js);
        $this->assertStringContainsString('appinstalled', $js);
        $this->assertStringContainsString('kv_pwa_installed', $js);
        // timing + dismissal contract
        $this->assertStringContainsString('2000', $js);   // ~2s delay
        $this->assertStringContainsString('30000', $js);  // 30s max
        $this->assertStringContainsString('kv_a2hs_dismissed', $js); // × — permanent
        $this->assertStringContainsString('kv_a2hs_snooze', $js);    // 30s timeout — 24h only
        $this->assertStringContainsString('kv_a2hs_shown', $js);     // once per session
        // cleanup
        $this->assertStringContainsString('clearTimeout', $js);
        $this->assertStringContainsString('removeEventListener', $js);
    }

    // ---------- PWA readiness ----------

    public function test_manifest_is_valid_and_installable(): void
    {
        $path = public_path('manifest.webmanifest');
        $this->assertFileExists($path);

        $m = json_decode(file_get_contents($path), true);
        $this->assertIsArray($m);
        foreach (['name', 'short_name', 'start_url', 'scope', 'display', 'theme_color', 'background_color', 'icons'] as $key) {
            $this->assertArrayHasKey($key, $m, "manifest missing {$key}");
        }
        $this->assertSame('standalone', $m['display']);
        $this->assertSame('/dashboard', $m['start_url']);
        $this->assertSame('/', $m['scope']);

        // installability needs 192 + 512 icons; every declared file must exist
        $sizes = array_column($m['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertContains('maskable', array_column($m['icons'], 'purpose'));
        foreach ($m['icons'] as $icon) {
            $file = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($file, "Missing icon {$icon['src']}");
            $this->assertStringStartsWith("\x89PNG", file_get_contents($file));
        }
    }

    public function test_manifest_and_icons_linked_in_customer_layouts(): void
    {
        $this->seedMarketplace();
        $html = $this->actingAs($this->customer())->get('/dashboard')->getContent();
        $this->assertStringContainsString('manifest.webmanifest', $html);
        $this->assertStringContainsString('apple-touch-icon', $html);
        $this->assertStringContainsString('theme-color', $html);
    }

    public function test_service_worker_references_real_icon(): void
    {
        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString('/icons/icon-192.png', $sw);
        $this->assertStringNotContainsString('/icon.png', $sw); // dead path removed
        $this->assertFileExists(public_path('icons/icon-192.png'));
    }
}
