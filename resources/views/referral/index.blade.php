<x-app-layout>
    <x-slot name="title">Referrals</x-slot>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-extrabold text-ink-900 tracking-tight">Refer &amp; earn</h1>
            <p class="mt-1 text-sm text-ink-500">Invite friends — earn wallet credit when they make their first purchase.</p>
        </div>

        {{-- Share card --}}
        <div class="kv-card overflow-hidden">
            <div class="p-6 sm:p-8 bg-gradient-to-br from-ink-900 via-ink-900 to-brand-900 relative">
                <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full bg-brand-500/15 blur-3xl pointer-events-none"></div>
                <div class="relative">
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-400">Your referral link</p>
                    <div class="mt-3 flex flex-col sm:flex-row gap-3">
                        <div class="flex-1 min-w-0 flex items-center rounded-xl bg-white/10 border border-white/15 px-4 py-3">
                            <i class="fas fa-link text-ink-400 mr-3 text-sm shrink-0"></i>
                            <span class="text-sm font-mono text-white truncate min-w-0 select-all" id="ref-link">{{ $referralLink }}</span>
                        </div>
                        <button type="button" onclick="kvCopy(document.getElementById('ref-link').textContent, this)"
                                class="kv-btn bg-brand-500 text-white hover:bg-brand-400 shrink-0">
                            <i class="fas fa-copy"></i> Copy link
                        </button>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <span class="text-xs text-ink-400">Or share your code:</span>
                        <button type="button" onclick="kvCopy('{{ $referralCode }}', this)"
                                class="kv-badge bg-white/10 text-white font-mono !text-sm !px-3.5 !py-1.5 hover:bg-white/20 transition border border-white/15">
                            {{ $referralCode }} <i class="fas fa-copy ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Total referrals" :value="$stats['total_referrals']" icon="fa-users" accent="brand" />
            <x-stat-card label="Qualified" :value="$stats['qualified_referrals']" icon="fa-circle-check" accent="green" />
            <x-stat-card label="Pending" :value="$stats['pending_referrals']" icon="fa-clock" accent="amber" />
            <x-stat-card label="Total earned" :value="xaf($stats['total_rewards'])" icon="fa-gift" accent="violet" />
        </div>

        {{-- How it works --}}
        <div class="kv-card p-6">
            <h2 class="font-bold text-ink-900 text-sm mb-4">How rewards work</h2>
            <div class="grid sm:grid-cols-3 gap-4">
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-brand-50 text-brand-600 grid place-items-center text-xs font-bold shrink-0">1</div>
                    <div>
                        <p class="text-sm font-semibold text-ink-900">Share your link</p>
                        <p class="text-xs text-ink-500 mt-0.5">Friends sign up using your referral link or code.</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-brand-50 text-brand-600 grid place-items-center text-xs font-bold shrink-0">2</div>
                    <div>
                        <p class="text-sm font-semibold text-ink-900">They purchase</p>
                        <p class="text-xs text-ink-500 mt-0.5">Their first number purchase qualifies your referral.</p>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full bg-brand-50 text-brand-600 grid place-items-center text-xs font-bold shrink-0">3</div>
                    <div>
                        <p class="text-sm font-semibold text-ink-900">You earn credit</p>
                        <p class="text-xs text-ink-500 mt-0.5">Reward lands in your wallet automatically.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6 items-start">
            {{-- Referred users --}}
            <div class="kv-card overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-ink-100">
                    <h2 class="font-bold text-ink-900 text-sm">Referred users</h2>
                </div>
                @if($referrals->isEmpty())
                    <x-empty-state icon="fa-user-plus" title="No referrals yet" message="Share your link to start earning rewards." />
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach($referrals as $referral)
                            <li class="flex items-center gap-3.5 px-5 sm:px-6 py-3.5">
                                <div class="w-9 h-9 rounded-full bg-ink-100 grid place-items-center text-xs font-bold text-ink-600 shrink-0">
                                    {{ strtoupper(substr($referral->referredUser->name ?? 'U', 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-ink-900 truncate">{{ $referral->referredUser->name ?? 'User' }}</p>
                                    <p class="text-xs text-ink-400">Joined {{ $referral->created_at->format('M d, Y') }}</p>
                                </div>
                                @if($referral->isQualified())
                                    <span class="kv-badge bg-emerald-100 text-emerald-700"><i class="fas fa-check"></i> Qualified</span>
                                @else
                                    <span class="kv-badge bg-amber-100 text-amber-700">Pending</span>
                                @endif
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
                <div class="px-5 sm:px-6 py-4 border-b border-ink-100">
                    <h2 class="font-bold text-ink-900 text-sm">Rewards</h2>
                </div>
                @if($rewards->isEmpty())
                    <x-empty-state icon="fa-gift" title="No rewards yet" message="Rewards appear here when your referrals qualify." />
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach($rewards as $reward)
                            <li class="flex items-center gap-3.5 px-5 sm:px-6 py-3.5">
                                <div class="w-9 h-9 rounded-xl bg-violet-50 text-violet-600 grid place-items-center shrink-0">
                                    <i class="fas fa-gift text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-ink-900 truncate">{{ $reward->description }}</p>
                                    <p class="text-xs text-ink-400 font-mono">{{ $reward->order->order_id ?? '' }} · {{ $reward->created_at->format('M d, Y') }}</p>
                                </div>
                                <div class="text-right">
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
</x-app-layout>
