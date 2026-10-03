<x-admin-layout>
    <x-slot name="title">Transactions</x-slot>
    <x-slot name="header">Wallet transactions</x-slot>

    <div class="space-y-5">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <x-stat-card label="Deposits" :value="xaf($totals['deposits'])" icon="fa-arrow-down" accent="green" />
            <x-stat-card label="Purchases" :value="xaf($totals['purchases'])" icon="fa-basket-shopping" accent="brand" />
            <x-stat-card label="Refunds" :value="xaf($totals['refunds'])" icon="fa-rotate-left" accent="amber" />
            <x-stat-card label="Adjustments" :value="xaf($totals['adjustments'])" icon="fa-sliders" accent="violet" />
        </div>

        <form method="GET" action="{{ route('admin.transactions.index') }}" class="kv-card p-4 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 text-xs"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Transaction ID, reference, or email…" class="kv-input !pl-9">
            </div>
            <select name="type" class="kv-input sm:w-40">
                <option value="">All types</option>
                @foreach($types as $t)
                    <option value="{{ $t }}" @selected(request('type') === $t)>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
            <select name="status" class="kv-input sm:w-40">
                <option value="">All statuses</option>
                @foreach(['completed','pending','failed'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="kv-btn-secondary shrink-0"><i class="fas fa-filter"></i> Filter</button>
        </form>

        <div class="kv-card overflow-hidden">
            @if($transactions->isEmpty())
                <x-empty-state icon="fa-money-bill-transfer" title="No transactions found" />
            @else
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-ink-50/70 border-b border-ink-100">
                            <tr>
                                <th class="kv-th">Transaction</th>
                                <th class="kv-th">Customer</th>
                                <th class="kv-th">Type</th>
                                <th class="kv-th">Direction</th>
                                <th class="kv-th">Related</th>
                                <th class="kv-th">Description</th>
                                <th class="kv-th">Amount</th>
                                <th class="kv-th">Status</th>
                                <th class="kv-th">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach($transactions as $tx)
                                @php
                                    $credit = (float) $tx->amount > 0;
                                    $relatedOrder = $relatedOrders[$tx->reference ?? ($tx->metadata['order_id'] ?? '')] ?? null;
                                @endphp
                                <tr class="hover:bg-ink-50/60 transition">
                                    <td class="kv-td font-mono text-xs font-semibold text-ink-900">{{ $tx->transaction_id }}</td>
                                    <td class="kv-td text-xs text-ink-600">{{ $tx->user->email ?? '—' }}</td>
                                    <td class="kv-td"><span class="kv-badge {{ transactionTypeColor($tx->type) }}">{{ ucfirst($tx->type) }}</span></td>
                                    <td class="kv-td">
                                        <span class="kv-badge {{ $credit ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                            {{ $credit ? 'CREDIT' : 'DEBIT' }}
                                        </span>
                                    </td>
                                    <td class="kv-td text-xs">
                                        @if($relatedOrder)
                                            <a href="{{ route('admin.orders.show', $relatedOrder) }}" class="font-mono text-brand-600 hover:text-brand-700">{{ $relatedOrder->order_id }}</a>
                                        @elseif($tx->reference)
                                            <span class="font-mono text-ink-500">{{ $tx->reference }}</span>
                                        @else
                                            <span class="text-ink-300">—</span>
                                        @endif
                                    </td>
                                    <td class="kv-td text-xs text-ink-600 max-w-[16rem] truncate">{{ $tx->description ?? '—' }}</td>
                                    <td class="kv-td font-bold {{ $credit ? 'text-emerald-600' : 'text-ink-900' }}">
                                        {{ $credit ? '+' : '−' }}{{ xaf(abs($tx->amount)) }}
                                    </td>
                                    <td class="kv-td"><x-status-badge :status="$tx->status" /></td>
                                    <td class="kv-td text-xs text-ink-500">{{ $tx->created_at->format('M d, H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <ul class="md:hidden divide-y divide-ink-100">
                    @foreach($transactions as $tx)
                        @php $credit = (float) $tx->amount > 0; @endphp
                        <li class="px-5 py-4">
                            <div class="flex justify-between items-center gap-3">
                                <span class="kv-badge {{ transactionTypeColor($tx->type) }}">{{ ucfirst($tx->type) }} · {{ $credit ? 'CREDIT' : 'DEBIT' }}</span>
                                <span class="text-xs font-bold {{ $credit ? 'text-emerald-600' : 'text-ink-900' }}">
                                    {{ $credit ? '+' : '−' }}{{ xaf(abs($tx->amount)) }}
                                </span>
                            </div>
                            <p class="mt-1.5 text-xs text-ink-600 truncate">{{ $tx->description ?? $tx->transaction_id }}</p>
                            <p class="mt-0.5 text-[11px] text-ink-400">{{ $tx->user->email ?? '—' }} · {{ $tx->created_at->format('M d, H:i') }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($transactions->hasPages())
            <div>{{ $transactions->links() }}</div>
        @endif
    </div>
</x-admin-layout>
