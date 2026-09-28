<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(protected AuditService $auditService) {}

    public function index(Request $request)
    {
        $referrals = Referral::with(['referrer', 'referredUser'])
            ->latest()
            ->paginate(25, ['*'], 'referrals')
            ->withQueryString();

        $rewards = ReferralReward::with(['user', 'order'])
            ->latest()
            ->paginate(25, ['*'], 'rewards')
            ->withQueryString();

        $stats = [
            'total' => Referral::count(),
            'qualified' => Referral::whereNotNull('qualified_at')->count(),
            'rewards_paid' => ReferralReward::where('status', 'approved')->sum('reward_amount'),
            'rewards_count' => ReferralReward::count(),
        ];

        $settings = [
            'reward_type' => Setting::get('referral_reward_type', 'percentage'),
            'reward_value' => Setting::get('referral_reward_value', 10),
            'require_purchase' => (bool) Setting::get('referral_require_purchase', 1),
        ];

        return view('admin.referrals.index', compact('referrals', 'rewards', 'stats', 'settings'));
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'reward_type' => 'required|in:percentage,fixed',
            'reward_value' => 'required|numeric|min:0',
            'require_purchase' => 'boolean',
        ]);

        Setting::set('referral_reward_type', $validated['reward_type'], 'string', 'referral');
        Setting::set('referral_reward_value', (string) $validated['reward_value'], 'float', 'referral');
        Setting::set('referral_require_purchase', $request->boolean('require_purchase') ? '1' : '0', 'boolean', 'referral');

        $this->auditService->log('referral.settings.update', null, null, $validated);

        return back()->with('success', 'Referral settings updated.');
    }
}
