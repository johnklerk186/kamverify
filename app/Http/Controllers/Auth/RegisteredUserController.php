<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        // Capture ?ref= links shared by referrers
        if ($request->filled('ref')) {
            session(['referral_code' => strtoupper(trim($request->query('ref')))]);
        }

        return view('auth.register', [
            'referralCode' => session('referral_code'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, ReferralService $referralService): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'referral_code' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer',
        ]);

        // Apply referral code if provided (session fallback for ?ref= links)
        $referralCode = $request->input('referral_code') ?: session('referral_code');
        if ($referralCode) {
            try {
                $referrer = $referralService->validateReferralCode($referralCode);
                if ($referrer) {
                    $referralService->createReferral(
                        $referrer,
                        $user,
                        $referrer->referral_code,
                        $request->ip(),
                        substr((string) $request->userAgent(), 0, 500)
                    );
                }
            } catch (\Exception $e) {
                // Referral failures must never block registration
                Log::warning('Referral attach failed during registration', [
                    'user_id' => $user->id,
                    'code' => $referralCode,
                    'error' => $e->getMessage(),
                ]);
            }
            session()->forget('referral_code');
        }

        event(new Registered($user));

        // Admin alert — dedupe key per user makes repeat submissions safe.
        app(\App\Services\AdminMailer::class)->send(
            'user.registered:' . $user->id,
            'KamVerify — New Customer Registration',
            'A new customer registered',
            array_filter([
                'Name' => $user->name,
                'Email' => $user->email,
                'Registered' => $user->created_at?->toDayDateTimeString() ?? now()->toDayDateTimeString(),
                'Referred by' => $user->referred_by ? 'Yes (referral code used)' : null,
            ], fn ($v) => $v !== null)
        );

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
