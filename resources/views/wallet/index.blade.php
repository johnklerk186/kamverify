<x-app-layout>
    <x-slot name="title">Wallet</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-ink-900 tracking-tight">Wallet</h1>
                <p class="mt-1 text-sm text-ink-500">Your balance, deposits, and spending history.</p>
            </div>
            <a href="{{ route('wallet.deposit') }}" class="kv-btn-primary shrink-0"><i class="fas fa-plus"></i> Deposit funds</a>
        </div>

        @if($sandbox)
            <div class="rounded-xl bg-sky-50 border border-sky-200/70 px-4 py-3 flex items-start gap-3">
                <i class="fas fa-flask text-sky-600 mt-0.5"></i>
                <p class="text-sm text-sky-800">
                    <strong>Sandbox mode.</strong> Deposits are simulated and credit instantly — no real money is charged.
                    Live Fapshi payments activate automatically once production credentials are configured.
                </p>
            </div>
        @endif

        {{-- Balance cards --}}
        <div class="grid sm:grid-cols-3 gap-4">
            <div class="kv-card p-6 bg-gradient-to-br from-ink-900 to-brand-900 !border-0 relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-brand-500/15 blur-2xl pointer-events-none"></div>
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-400">Available balance</p>
                <p class="mt-1.5 text-3xl font-extrabold text-white">{{ xaf($wallet->balance) }}</p>
            </div>
            <x-stat-card label="Total deposited" :value="xaf($wallet->total_deposited)" icon="fa-arrow-down" accent="green" />
            <x-stat-card label="Total spent" :value="xaf($wallet->total_withdrawn)" icon="fa-arrow-up" accent="brand" />
        </div>

        {{-- Pending payments --}}
        @if($pendingPayments->isNotEmpty())
            <div class="kv-card">
                <div class="px-5 sm:px-6 py-4 border-b border-ink-100">
                    <h2 class="font-bold text-ink-900 text-sm">Pending payments</h2>
                </div>
                <ul class="divide-y divide-ink-100">
                    @foreach($pendingPayments as $payment)
                        <li class="flex items-center gap-4 px-5 sm:px-6 py-4" x-data="pendingPayment({{ $payment->id }})" x-init="poll()">
                            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 grid place-items-center shrink-0">
                                <i class="fas fa-clock text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-ink-900">{{ xaf($payment->amount) }} via {{ $payment->provider === 'fapshi' ? 'MTN Mobile Money' : ucfirst($payment->provider) }}</p>
                                <p class="text-xs text-ink-400" x-text="hint">{{ $payment->created_at->diffForHumans() }} · awaiting phone confirmation</p>
                            </div>
                            <x-status-badge status="pending" />
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Transactions --}}
        <div class="kv-card overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-ink-100 flex items-center justify-between">
                <h2 class="font-bold text-ink-900 text-sm">Transaction history</h2>
                <span class="text-xs text-ink-400">{{ $transactions->count() }} recent</span>
            </div>
            @if($transactions->isEmpty())
                <x-empty-state icon="fa-arrow-right-arrow-left" title="No transactions yet" message="Deposits, purchases, and refunds will appear here.">
                    <a href="{{ route('wallet.deposit') }}" class="kv-btn-primary"><i class="fas fa-plus"></i> Make a deposit</a>
                </x-empty-state>
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach($transactions as $tx)
                        @php $credit = in_array($tx->type, ['deposit', 'refund', 'reward', 'adjustment']); @endphp
                        <li class="flex items-center gap-4 px-5 sm:px-6 py-3.5">
                            <span class="w-9 h-9 rounded-xl grid place-items-center shrink-0 {{ transactionTypeColor($tx->type) }}">
                                <i class="fas {{ $credit ? 'fa-arrow-down' : 'fa-arrow-up' }} text-xs"></i>
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-semibold text-ink-900 truncate">{{ $tx->description ?? ucfirst($tx->type) }}</p>
                                    <span class="kv-badge {{ transactionTypeColor($tx->type) }}">{{ ucfirst($tx->type) }}</span>
                                </div>
                                <p class="mt-0.5 text-xs text-ink-400 font-mono">{{ $tx->transaction_id }} · {{ $tx->created_at->format('M d, Y H:i') }}</p>
                            </div>
                            <span class="text-sm font-bold {{ $credit ? 'text-emerald-600' : 'text-ink-900' }}">
                                {{ $credit ? '+' : '−' }}{{ xaf($tx->amount) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <script>
        // Poll pending payments until the provider confirms — the wallet
        // credits only after backend verification.
        function pendingPayment(id) {
            return {
                hint: 'Approve the payment request on your phone…',
                poll() {
                    const check = async () => {
                        try {
                            const res = await fetch('/wallet/payments/' + id + '/status', { headers: { 'Accept': 'application/json' } });
                            const data = await res.json();
                            if (data.completed || ['failed', 'cancelled', 'expired'].includes(data.status)) {
                                window.location.reload();
                            }
                        } catch (e) { /* keep polling */ }
                    };
                    setInterval(check, 15000);
                    setTimeout(check, 3000);
                }
            }
        }
    </script>
</x-app-layout>
