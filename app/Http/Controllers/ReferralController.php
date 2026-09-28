<?php

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReferralController extends Controller
{
    protected ReferralService $referralService;

    public function __construct(ReferralService $referralService)
    {
        $this->referralService = $referralService;
    }

    public function index()
    {
        $user = Auth::user();
        $referralLink = $this->referralService->getReferralLink($user);
        $referralCode = $user->generateReferralCode();
        $stats = $this->referralService->getReferralStats($user);

        $referrals = \App\Models\Referral::where('referrer_id', $user->id)
            ->with('referredUser:id,name,created_at')
            ->latest()
            ->paginate(10, ['*'], 'referrals');

        $rewards = \App\Models\ReferralReward::where('user_id', $user->id)
            ->with('order:id,order_id')
            ->latest()
            ->paginate(10, ['*'], 'rewards');

        return view('referral.index', compact('referralLink', 'referralCode', 'stats', 'referrals', 'rewards'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'referral_code' => 'required|string|max:20',
        ]);

        try {
            $referrer = $this->referralService->validateReferralCode($request->referral_code);
            
            if (!$referrer) {
                return back()->with('error', 'Invalid referral code');
            }

            // Store referral code in session for registration
            session(['referral_code' => $request->referral_code, 'referrer_id' => $referrer->id]);

            return redirect()->route('register')->with('success', 'Referral code applied! Complete registration to earn rewards.');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to apply referral code: ' . $e->getMessage());
        }
    }
}