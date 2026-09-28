<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    /**
     * Protects the /admin area with the dedicated 'admin' session guard.
     * Guests are sent to the admin login page; authenticated non-admins
     * get a hard 403 — customer sessions can never enter this area.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('admin')->user();

        if (!$user) {
            if ($request->expectsJson()) {
                abort(403, 'Unauthorized access.');
            }

            if ($request->isMethod('GET')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('admin.login');
        }

        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        // Make Auth::user() resolve to the admin guard for this request
        Auth::shouldUse('admin');

        return $next($request);
    }
}
