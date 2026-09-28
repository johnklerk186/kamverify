<?php

namespace App\Http\Controllers;

use App\Services\DemoDataSeeder;
use App\Support\DemoAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Public demo entry point. Signs the visitor into a dedicated,
 * clearly-marked demo customer account seeded with test data.
 *
 * Security notes:
 * - Only reachable when config('app.demo_enabled') is true — off in
 *   production unless DEMO_ENABLED=true is explicitly set.
 * - The demo account is a normal customer (role=customer) with a random
 *   unknown password — it cannot reach /admin or other users' data,
 *   because every existing authorization check still applies.
 * - A signed kv_demo cookie is issued so the DemoAuthenticate
 *   middleware can authenticate the demo account per-request even if
 *   the visitor's browser drops or races the session cookie. The
 *   token is an HMAC bound to the demo user — it is worthless for any
 *   other account and cannot be forged without APP_KEY.
 * - demo_mode marks the session/request so destructive mutations
 *   (profile/password/account) are blocked and the UI shows a
 *   DEMO ENVIRONMENT indicator.
 */
class DemoController extends Controller
{
    public function enter(DemoDataSeeder $seeder): RedirectResponse
    {
        if (!DemoAccess::enabled()) {
            return redirect('/')->withoutCookie(DemoAccess::COOKIE);
        }

        // Sign out any existing session before entering the demo.
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        $demo = $seeder->demoUser();
        $seeder->seed($demo);

        Auth::guard('web')->login($demo);

        request()->session()->regenerate();
        request()->session()->put('demo_mode', true);

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to the KamVerify demo — everything here uses sample data.')
            ->cookie(cookie(DemoAccess::COOKIE, DemoAccess::tokenFor($demo), 60 * 24 * 7, '/', null, null, true, false, 'lax'));
    }
}
