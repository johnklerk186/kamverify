<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>

    <div class="space-y-6">
        {{-- Balance hero --}}
        <div class="kv-card overflow-hidden">
            <div class="p-6 sm:p-8 bg-gradient-to-br from-ink-900 via-ink-900 to-brand-900 relative">
                <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full bg-brand-500/15 blur-3xl pointer-events-none"></div>
                <div class="relative flex flex-col sm:flex-row sm:items-end sm:justify-between gap-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-ink-400">Wallet balance</p>
                        <p class="mt-1.5 text-4xl font-extrabold text-white tracking-tight">
                            {{ xaf($wallet->balance) }}
                        </p>
                        <p class="mt-1 text-xs text-ink-400">
                            {{ xaf($wallet->total_deposited) }} deposited · {{ xaf($wallet->total_withdrawn) }} spent
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <a href="{{ route('wallet.deposit') }}" class="kv-btn bg-brand-500 text-white hover:bg-brand-400 focus:ring-brand-400">
                            <i class="fas fa-plus"></i> Deposit
                        </a>
                        <a href="{{ route('orders.create') }}" class="kv-btn bg-white/10 text-white hover:bg-white/20 focus:ring-white/30 border border-white/10">
                            <i class="fas fa-cart-shopping"></i> Buy number
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Active orders" :value="$orderStats['active_orders']" icon="fa-bolt" accent="amber" :href="route('orders.index', ['status' => 'active'])" />
            <x-stat-card label="Total orders" :value="$orderStats['total_orders']" icon="fa-receipt" accent="brand" :href="route('orders.index')" />
            <x-stat-card label="Completed" :value="$orderStats['completed_orders']" icon="fa-circle-check" accent="green" :href="route('orders.index', ['status' => 'completed'])" />
            <x-stat-card label="Cancelled" :value="$orderStats['cancelled_orders']" icon="fa-circle-xmark" accent="red" :href="route('orders.index', ['status' => 'cancelled'])" />
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            {{-- Active orders --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="kv-card">
                    <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-ink-100">
                        <h2 class="font-bold text-ink-900">Active orders</h2>
                        <a href="{{ route('orders.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">View all</a>
                    </div>
                    @if($activeOrders->isEmpty())
                        <x-empty-state icon="fa-mobile-screen" title="No active numbers" message="Purchase a virtual number to start receiving SMS verification codes.">
                            <a href="{{ route('orders.create') }}" class="kv-btn-primary"><i class="fas fa-plus"></i> Buy a number</a>
                        </x-empty-state>
                    @else
                        <ul class="divide-y divide-ink-100">
                            @foreach($activeOrders as $order)
                                <li>
                                    <a href="{{ route('orders.show', $order) }}" class="flex items-center gap-4 px-5 sm:px-6 py-4 hover:bg-ink-50 transition">
                                        <span class="text-xl">{{ countryFlag($order->country->code) }}</span>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                @php [$sIcon, $sColor] = serviceIcon($order->service->slug); @endphp
                                                <i class="{{ $sIcon }} {{ $sColor }} text-sm shrink-0"></i>
                                                <p class="font-semibold text-ink-900 text-sm truncate min-w-0">{{ $order->service->name }}</p>
                                                <span class="text-xs text-ink-400 truncate min-w-0 shrink">· {{ $order->country->name }}</span>
                                            </div>
                                            <p class="mt-0.5 text-xs font-mono text-ink-500 truncate">{{ $order->phone_number ?? 'Assigning…' }}</p>
                                        </div>
                                        <x-status-badge :status="$order->status" />
                                        <i class="fas fa-chevron-right text-xs text-ink-300"></i>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Recent SMS --}}
                <div class="kv-card">
                    <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-ink-100">
                        <h2 class="font-bold text-ink-900">Recent SMS</h2>
                    </div>
                    @if($recentSms->isEmpty())
                        <x-empty-state icon="fa-message" title="No SMS yet" message="Messages sent to your active numbers will appear here." />
                    @else
                        <ul class="divide-y divide-ink-100">
                            @foreach($recentSms as $sms)
                                <li class="px-5 sm:px-6 py-4 flex items-start gap-4">
                                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 grid place-items-center shrink-0">
                                        <i class="fas fa-message text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-ink-800 break-words">{{ Str::limit($sms->message, 90) }}</p>
                                        <p class="mt-1 text-xs text-ink-400">From {{ $sms->sender }} · {{ $sms->received_at->diffForHumans() }}</p>
                                    </div>
                                    @if($sms->otp_code)
                                        <button type="button" onclick="kvCopy('{{ $sms->otp_code }}', this)"
                                                class="kv-badge bg-emerald-100 text-emerald-700 font-mono hover:bg-emerald-200 transition shrink-0">
                                            <i class="fas fa-copy"></i> {{ $sms->otp_code }}
                                        </button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Right column --}}
            <div class="space-y-6">
                {{-- Quick actions --}}
                <div class="kv-card p-5">
                    <h2 class="font-bold text-ink-900 text-sm">Quick actions</h2>
                    <div class="mt-4 grid grid-cols-2 gap-2.5">
                        <a href="{{ route('orders.create') }}" class="flex flex-col items-center gap-2 rounded-xl border border-ink-200/70 px-3 py-4 hover:border-brand-300 hover:bg-brand-50/50 transition text-center">
                            <i class="fas fa-cart-shopping text-brand-600"></i>
                            <span class="text-xs font-semibold text-ink-700">Buy number</span>
                        </a>
                        <a href="{{ route('wallet.deposit') }}" class="flex flex-col items-center gap-2 rounded-xl border border-ink-200/70 px-3 py-4 hover:border-brand-300 hover:bg-brand-50/50 transition text-center">
                            <i class="fas fa-wallet text-brand-600"></i>
                            <span class="text-xs font-semibold text-ink-700">Deposit</span>
                        </a>
                        <a href="{{ route('support.create') }}" class="flex flex-col items-center gap-2 rounded-xl border border-ink-200/70 px-3 py-4 hover:border-brand-300 hover:bg-brand-50/50 transition text-center">
                            <i class="fas fa-headset text-brand-600"></i>
                            <span class="text-xs font-semibold text-ink-700">Support</span>
                        </a>
                        <a href="{{ route('referral.index') }}" class="flex flex-col items-center gap-2 rounded-xl border border-ink-200/70 px-3 py-4 hover:border-brand-300 hover:bg-brand-50/50 transition text-center">
                            <i class="fas fa-gift text-brand-600"></i>
                            <span class="text-xs font-semibold text-ink-700">Refer &amp; earn</span>
                        </a>
                    </div>
                </div>

                {{-- Recent transactions --}}
                <div class="kv-card">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-ink-100">
                        <h2 class="font-bold text-ink-900 text-sm">Transactions</h2>
                        <a href="{{ route('wallet.index') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">View all</a>
                    </div>
                    @if($recentTransactions->isEmpty())
                        <div class="py-8 text-center text-sm text-ink-400">No transactions yet</div>
                    @else
                        <ul class="divide-y divide-ink-100">
                            @foreach($recentTransactions->take(6) as $tx)
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="kv-badge {{ transactionTypeColor($tx->type) }} !px-2 w-8 h-8 !rounded-lg justify-center">
                                        <i class="fas {{ $tx->type === 'deposit' || $tx->type === 'refund' || $tx->type === 'reward' ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-ink-800 truncate">{{ $tx->description ?? ucfirst($tx->type) }}</p>
                                        <p class="text-xs text-ink-400">{{ $tx->created_at->diffForHumans() }}</p>
                                    </div>
                                    <span class="text-sm font-semibold {{ in_array($tx->type, ['deposit','refund','reward']) ? 'text-emerald-600' : 'text-ink-900' }}">
                                        {{ in_array($tx->type, ['deposit','refund','reward']) ? '+' : '-' }}{{ xaf($tx->amount) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
