<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Admin accounts must use the dedicated admin portal — never
        // allow an admin into the customer dashboard as a normal user.
        if ($request->user()->isAdmin()) {
            Auth::guard('web')->logout();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->with('error', 'Administrator accounts must sign in through the admin portal.');
        }

        $request->session()->regenerate();

        // One-shot notice about storefront services in a temporary
        // outage — shown once per login, dismissed with "Got it".
        $outage = \App\Models\Service::where('temporarily_unavailable', true)
            ->customerVisible()
            ->pluck('name');
        if ($outage->isNotEmpty()) {
            $request->session()->flash('service_outage', $outage->all());
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/')->withoutCookie(\App\Support\DemoAccess::COOKIE);
    }
}
