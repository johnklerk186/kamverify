<x-admin-layout>
    <x-slot name="title">Payment {{ $payment->payment_id }}</x-slot>
    <x-slot name="header">Payment detail</x-slot>

    <div class="max-w-4xl space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.payments.index') }}" class="w-9 h-9 rounded-xl border border-ink-200 bg-white grid place-items-center text-ink-500 hover:text-ink-800 transition">
                    <i class="fas fa-arrow-left text-sm"></i>
                </a>
                <div>
                    <h1 class="text-xl font-extrabold text-ink-900 font-mono tracking-tight">{{ $payment->payment_id }}</h1>
                    <p class="text-xs text-ink-500">{{ $payment->created_at->format('M d, Y H:i:s') }}</p>
                </div>
            </div>
            <x-status-badge :status="$payment->status" />
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
            <x-stat-card label="Amount" :value="xaf($payment->amount)" icon="fa-sack-dollar" accent="brand" />
            <x-stat-card label="Provider" :value="ucfirst($payment->provider)" icon="fa-credit-card" accent="blue" />
            <x-stat-card label="Currency" :value="$payment->currency ?? 'USD'" icon="fa-coins" accent="violet" />
        </div>

        <div class="grid lg:grid-cols-2 gap-5 items-start">
            <div class="kv-card p-5">
                <h2 class="font-bold text-ink-900 text-sm mb-4">Details</h2>
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Customer</dt><dd class="text-ink-900 text-right">{{ $payment->user->email ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Provider ref</dt><dd class="font-mono text-xs text-ink-900 text-right break-all">{{ $payment->provider_payment_id ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Wallet tx</dt><dd class="font-mono text-xs text-ink-900 text-right break-all">{{ $payment->walletTransaction->transaction_id ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-500">Completed at</dt><dd class="text-ink-900">{{ $payment->completed_at?->format('M d, Y H:i') ?? '—' }}</dd></div>
                </dl>
            </div>

            @if($payment->provider_response || $payment->webhook_data)
                <div class="kv-card p-5">
                    <h2 class="font-bold text-ink-900 text-sm mb-3">Provider payload</h2>
                    <pre class="text-xs bg-ink-900 text-ink-100 rounded-xl p-4 overflow-x-auto font-mono max-h-80">{{ json_encode($payment->webhook_data ?? $payment->provider_response, JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
