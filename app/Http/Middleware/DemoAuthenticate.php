<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\DemoDataSeeder;
use App\Support\DemoAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Authenticates the demo account for the current request using the
 * signed kv_demo cookie — independent of session persistence, so the
 * demo survives browsers/contexts that drop or race session cookies.
 * Runs before the auth middleware on customer routes; a valid token
 * only ever grants the dedicated demo customer account.
 */
class DemoAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        if (!DemoAccess::enabled()) {
            return $next($request);
        }

        if (Auth::guard('web')->check()) {
            // Session-authenticated — flag demo sessions so views and
            // mutation guards can rely on the request attribute even
            // when the session store hiccups.
            if ($request->hasSession() && $request->session()->get('demo_mode')) {
                $request->attributes->set('demo_mode', true);
            }

            return $next($request);
        }

        $token = $request->cookie(DemoAccess::COOKIE);

        if ($token) {
            $demo = User::where('email', DemoDataSeeder::DEMO_EMAIL)
                ->where('role', 'customer')
                ->where('is_active', true)
                ->first();

            if ($demo && DemoAccess::tokenMatches($demo, $token)) {
                Auth::setUser($demo);
                $request->attributes->set('demo_mode', true);
            }
        }

        return $next($request);
    }
}
