<x-admin-layout>
    <x-slot name="title">Referrals</x-slot>
    <x-slot name="header">Referral program</x-slot>

    <div class="space-y-6">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Total referrals" :value="$stats['total']" icon="fa-users" accent="brand" />
            <x-stat-card label="Qualified" :value="$stats['qualified']" icon="fa-circle-check" accent="green" />
            <x-stat-card label="Rewards issued" :value="$stats['rewards_count']" icon="fa-gift" accent="violet" />
            <x-stat-card label="Rewards paid" :value="xaf($stats['rewards_paid'])" icon="fa-sack-dollar" accent="amber" />
        </div>

        {{-- Settings --}}
        <div class="kv-card p-6">
            <h2 class="font-bold text-ink-900 text-sm">Reward settings</h2>
            <p class="mt-1 text-xs text-ink-500">Rewards are credited to the referrer's wallet when the referred user makes a qualifying purchase.</p>
            <form method="POST" action="{{ route('admin.referrals.settings') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf @method('PUT')
                <div>
                    <label class="kv-label !mb-1 text-xs">Reward type</label>
                    <select name="reward_type" class="kv-input !py-2 w-40">
                        <option value="percentage" @selected($settings['reward_type'] === 'percentage')>% of purchase</option>
                        <option value="fixed" @selected($settings['reward_type'] === 'fixed')>Fixed amount $</option>
                    </select>
                </div>
                <div>
                    <label class="kv-label !mb-1 text-xs">Value</label>
                    <input type="number" name="reward_value" value="{{ $settings['reward_value'] }}" step="0.01" min="0" required class="kv-input !py-2 w-28">
                </div>
                <label class="flex items-center gap-2.5 pb-2.5">
                    <input type="hidden" name="require_purchase" value="0">
                    <input type="checkbox" name="require_purchase" value="1" @checked($settings['require_purchase'])
                           class="w-4 h-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm font-medium text-ink-700">Require a purchase to qualify</span>
                </label>
                <button type="submit" class="kv-btn-primary !py-2 text-xs"><i class="fas fa-check"></i> Save</button>
            </form>
        </div>

        <div class="grid lg:grid-cols-2 gap-6 items-start">
            {{-- Referrals --}}
            <div class="kv-card overflow-hidden">
                <div class="px-5 py-4 border-b border-ink-100"><h2 class="font-bold text-ink-900 text-sm">Referrals</h2></div>
                @if($referrals->isEmpty())
                    <x-empty-state icon="fa-user-plus" title="No referrals yet" />
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach($referrals as $r)
                            <li class="px-5 py-3.5">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-ink-900 truncate">{{ $r->referrer->email ?? '—' }}</p>
                                        <p class="text-xs text-ink-500 truncate">referred {{ $r->referredUser->email ?? '—' }}</p>
                                    </div>
                                    @if($r->isQualified())
                                        <span class="kv-badge bg-emerald-100 text-emerald-700">Qualified</span>
                                    @else
                                        <span class="kv-badge bg-amber-100 text-amber-700">Pending</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-[11px] text-ink-400 font-mono">{{ $r->referral_code }} · {{ $r->created_at->format('M d, Y') }}</p>
                            </li>
                        @endforeach
                    </ul>
                    @if($referrals->hasPages())
                        <div class="px-5 py-3 border-t border-ink-100">{{ $referrals->links() }}</div>
                    @endif
                @endif
            </div>

            {{-- Rewards --}}
            <div class="kv-card overflow-hidden">
                <div class="px-5 py-4 border-b border-ink-100"><h2 class="font-bold text-ink-900 text-sm">Rewards</h2></div>
                @if($rewards->isEmpty())
                    <x-empty-state icon="fa-gift" title="No rewards yet" />
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach($rewards as $reward)
                            <li class="flex items-center justify-between gap-3 px-5 py-3.5">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-ink-900 truncate">{{ $reward->user->email ?? '—' }}</p>
                                    <p class="text-[11px] text-ink-400 font-mono">{{ $reward->order->order_id ?? '—' }} · {{ $reward->created_at->format('M d, Y') }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-bold text-emerald-600">+{{ xaf($reward->reward_amount) }}</p>
                                    <x-status-badge :status="$reward->status" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    @if($rewards->hasPages())
                        <div class="px-5 py-3 border-t border-ink-100">{{ $rewards->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
