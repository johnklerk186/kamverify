<x-admin-layout>
    <x-slot name="title">{{ $user->name }}</x-slot>
    <x-slot name="header">Customer detail</x-slot>

    <div class="space-y-6">
        {{-- Header --}}
        <div class="kv-card p-6 flex flex-col sm:flex-row sm:items-center gap-5">
            <div class="flex items-center gap-4 flex-1 min-w-0">
                <div class="w-14 h-14 rounded-2xl bg-brand-600 text-white grid place-items-center text-xl font-extrabold shrink-0">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg font-extrabold text-ink-900 truncate">{{ $user->name }}</h1>
                    <p class="text-sm text-ink-500 truncate">{{ $user->email }}</p>
                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                        <x-status-badge :status="$user->is_active ? 'active' : 'suspended'" />
                        @if($user->hasVerifiedEmail())
                            <span class="kv-badge bg-emerald-100 text-emerald-700"><i class="fas fa-check"></i> Verified</span>
                        @endif
                        @if($user->referred_by)
                            <span class="kv-badge bg-violet-100 text-violet-700">Referred</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex gap-2 shrink-0">
                <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}">
                    @csrf @method('PUT')
                    <button type="submit" class="{{ $user->is_active ? 'kv-btn-danger' : 'kv-btn-primary' }}">
                        <i class="fas {{ $user->is_active ? 'fa-ban' : 'fa-circle-check' }}"></i>
                        {{ $user->is_active ? 'Suspend' : 'Activate' }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Balance" :value="xaf($wallet->balance ?? 0)" icon="fa-wallet" accent="brand" />
            <x-stat-card label="Deposited" :value="xaf($wallet->total_deposited ?? 0)" icon="fa-arrow-down" accent="green" />
            <x-stat-card label="Spent" :value="xaf($totalSpent ?? 0)" icon="fa-arrow-up" accent="amber" />
            <x-stat-card label="Orders" :value="$user->orders()->count()" icon="fa-receipt" accent="blue" />
        </div>

        <div class="grid lg:grid-cols-2 gap-6 items-start">
            {{-- Orders --}}
            <div class="kv-card overflow-hidden">
                <div class="px-5 py-4 border-b border-ink-100 flex items-center justify-between">
                    <h2 class="font-bold text-ink-900 text-sm">Recent orders</h2>
                    <a href="{{ route('admin.orders.index', ['search' => $user->email]) }}" class="text-xs font-semibold text-brand-600">All</a>
                </div>
                @if($orders->isEmpty())
                    <div class="py-8 text-center text-sm text-ink-400">No orders</div>
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach($orders as $order)
                            <li>
                                <a href="{{ route('admin.orders.show', $order) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-ink-50 transition">
                                    <span>{{ countryFlag($order->country->code ?? null) }}</span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-ink-900 truncate">{{ $order->service->name ?? '—' }} · {{ $order->country->name ?? '' }}</p>
                                        <p class="text-[11px] text-ink-400 font-mono">{{ $order->order_id }}</p>
                                    </div>
                                    <span class="text-xs font-bold text-ink-900">{{ xaf($order->selling_price) }}</span>
                                    <x-status-badge :status="$order->status" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Transactions --}}
            <div class="kv-card overflow-hidden">
                <div class="px-5 py-4 border-b border-ink-100 flex items-center justify-between">
                    <h2 class="font-bold text-ink-900 text-sm">Recent transactions</h2>
                    <a href="{{ route('admin.transactions.index', ['search' => $user->email]) }}" class="text-xs font-semibold text-brand-600">All</a>
                </div>
                @if($transactions->isEmpty())
                    <div class="py-8 text-center text-sm text-ink-400">No transactions</div>
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach($transactions as $tx)
                            @php $credit = in_array($tx->type, ['deposit','refund','reward']); @endphp
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="w-8 h-8 rounded-lg grid place-items-center {{ transactionTypeColor($tx->type) }}">
                                    <i class="fas {{ $credit ? 'fa-arrow-down' : 'fa-arrow-up' }} text-xs"></i>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-ink-800 truncate">{{ $tx->description ?? ucfirst($tx->type) }}</p>
                                    <p class="text-[11px] text-ink-400">{{ $tx->created_at->format('M d, H:i') }}</p>
                                </div>
                                <span class="text-xs font-bold {{ $credit ? 'text-emerald-600' : 'text-ink-900' }}">
                                    {{ $credit ? '+' : '−' }}{{ xaf($tx->amount) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Account info + wallet adjustment + danger zone --}}
        <div class="grid lg:grid-cols-3 gap-6 items-start">
            <div class="kv-card p-5">
                <h2 class="font-bold text-ink-900 text-sm mb-4">Account details</h2>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-500">User ID</dt><dd class="font-mono text-ink-900">#{{ $user->id }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Referral code</dt><dd class="font-mono text-ink-900">{{ $user->referral_code ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Registered</dt><dd class="text-ink-900">{{ $user->created_at->format('M d, Y H:i') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Email verified</dt><dd class="text-ink-900">{{ $user->hasVerifiedEmail() ? $user->email_verified_at->format('M d, Y') : 'No' }}</dd></div>
                </dl>
            </div>
            <div class="kv-card p-5">
                <h2 class="font-bold text-ink-900 text-sm">Wallet adjustment</h2>
                <p class="mt-1 text-xs text-ink-500">Credit or debit this customer's wallet. Whole XAF only — a debit can never exceed the balance.</p>
                <form method="POST" action="{{ route('admin.users.wallet-adjust', $user) }}" class="mt-4 space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 rounded-xl border border-ink-200 px-3 py-2.5 text-sm cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="radio" name="direction" value="credit" checked class="accent-emerald-600">
                            <span class="font-semibold text-emerald-700">Credit +</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-ink-200 px-3 py-2.5 text-sm cursor-pointer has-[:checked]:border-red-500 has-[:checked]:bg-red-50">
                            <input type="radio" name="direction" value="debit" class="accent-red-600">
                            <span class="font-semibold text-red-700">Debit −</span>
                        </label>
                    </div>
                    <div>
                        <input type="number" name="amount" min="1" step="1" required placeholder="Amount (XAF)"
                               class="kv-input !py-2.5 @error('amount') border-red-400 @enderror" value="{{ old('amount') }}">
                        @error('amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <input type="text" name="reason" maxlength="255" required placeholder="Reason (e.g. manual top-up, compensation)"
                               class="kv-input !py-2.5 @error('reason') border-red-400 @enderror" value="{{ old('reason') }}">
                        @error('reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="kv-btn-primary w-full !py-2.5 text-sm">
                        <i class="fas fa-coins"></i> Apply adjustment
                    </button>
                </form>
            </div>
            <div class="kv-card p-5 border-red-200/70" x-data="{ confirm: false }">
                <h2 class="font-bold text-red-700 text-sm">Danger zone</h2>
                <p class="mt-1 text-xs text-ink-500">Deleting removes the account, wallet, and all history permanently.</p>
                <button type="button" x-show="!confirm" @click="confirm = true" class="kv-btn-danger mt-4 !py-2 text-xs">
                    <i class="fas fa-trash"></i> Delete account
                </button>
                <form x-show="confirm" x-cloak method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-4 flex gap-2">
                    @csrf @method('DELETE')
                    <button type="submit" class="kv-btn-danger !py-2 text-xs flex-1">Confirm delete</button>
                    <button type="button" @click="confirm = false" class="kv-btn-secondary !py-2 text-xs flex-1">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
