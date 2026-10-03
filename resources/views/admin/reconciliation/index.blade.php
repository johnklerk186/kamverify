<x-admin-layout>
    <x-slot name="title">Reconciliation</x-slot>
    <x-slot name="header">Financial reconciliation</x-slot>

    @php
        $tx = $report['transactions'];
        $pl = $report['platform'];
        $pr = $report['provider'];
        $ledgerMatch = abs($pl['wallet_balances'] - $pl['ledger_net']) < 0.01;
        $mismatches = collect($report['customers'])->where('match', false);
    @endphp

    <div class="space-y-5">

        <div class="flex justify-end">
            <a href="{{ route('admin.reconciliation.facebook') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-ink-900 text-white text-sm font-semibold hover:bg-ink-800 transition">
                <i class="fa-brands fa-facebook"></i> Facebook/Meta compatibility audit
            </a>
        </div>

        {{-- Platform integrity --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Deposits" :value="xaf($pl['deposits'])" icon="fa-arrow-down" accent="green" sub="External money in (Fapshi)" />
            <x-stat-card label="Refunds" :value="xaf($pl['refunds'])" icon="fa-rotate-left" accent="amber" sub="Returned order money" />
            <x-stat-card label="Purchases" :value="xaf($pl['purchases'])" icon="fa-basket-shopping" accent="brand" sub="Order debits" />
            <x-stat-card label="Adjustments" :value="xaf($pl['adjustments'])" icon="fa-sliders" accent="violet" sub="Admin/reward/reversals" />
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Wallet balances" :value="xaf($pl['wallet_balances'])" icon="fa-wallet" accent="blue" />
            <x-stat-card label="Ledger net" :value="xaf($pl['ledger_net'])"
                :icon="$ledgerMatch ? 'fa-circle-check' : 'fa-triangle-exclamation'"
                :accent="$ledgerMatch ? 'green' : 'red'"
                :sub="$ledgerMatch ? 'MATCH — balances equal ledger' : 'DISCREPANCY ' . xaf($pl['wallet_balances'] - $pl['ledger_net'])" />
            <x-stat-card label="Refundable outstanding" :value="xaf($report['orders']['refundable_outstanding'])"
                icon="fa-hourglass-half" accent="amber" sub="Ended orders with no refund yet" />
            <x-stat-card label="Ambiguous records" :value="count($tx['ambiguous'])"
                icon="fa-circle-question" accent="ink" sub="Flagged for manual review — never auto-changed" />
        </div>

        {{-- HeroSMS provider reconciliation --}}
        <div class="kv-card p-5">
            <h3 class="text-sm font-bold text-ink-900 uppercase tracking-wider mb-4">
                <i class="fas fa-server text-ink-400 mr-2"></i>HeroSMS provider cost
            </h3>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                <div><p class="text-ink-500 text-xs">Activations</p><p class="font-bold text-ink-900">{{ $pr['activations'] }}</p>
                    <p class="text-xs text-ink-400">{{ $pr['completed'] }} completed · {{ $pr['wound_down'] }} wound down · {{ $pr['active'] }} active</p></div>
                <div><p class="text-ink-500 text-xs">Total provider cost</p><p class="font-bold text-ink-900">{{ xaf(usdToXaf($pr['total_cost'])) }}</p>
                    <p class="text-xs text-ink-400">${{ number_format($pr['total_cost'], 2) }} USD</p></div>
                <div><p class="text-ink-500 text-xs">Recovered by provider</p><p class="font-bold text-emerald-600">{{ xaf(usdToXaf($pr['recovered_cost'])) }}</p>
                    <p class="text-xs text-ink-400">{{ $pr['provider_released'] }} released/resolved</p></div>
                <div><p class="text-ink-500 text-xs">Consumed by provider</p><p class="font-bold text-red-600">{{ xaf(usdToXaf($pr['lost_cost'])) }}</p>
                    <p class="text-xs text-ink-400">{{ $pr['provider_consumed'] }} delivered activations</p></div>
            </div>
            @if($pr['provider_unrecorded'] > 0)
                <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                    <i class="fas fa-triangle-exclamation mr-1"></i>
                    <strong>{{ $pr['provider_unrecorded'] }} wound-down activation{{ $pr['provider_unrecorded'] === 1 ? '' : 's' }}</strong>
                    ({{ xaf(usdToXaf($pr['unverified_cost'])) }} of provider cost) have no recorded provider outcome —
                    these are historical orders from before outcome tracking. Cost recovery is unverified and is the most likely source of HeroSMS balance drain.
                </div>
            @endif
        </div>

        {{-- Per-customer integrity --}}
        <div class="kv-card overflow-hidden">
            <div class="px-5 py-4 border-b border-ink-100 flex items-center justify-between">
                <h3 class="text-sm font-bold text-ink-900 uppercase tracking-wider">
                    <i class="fas fa-scale-balanced text-ink-400 mr-2"></i>Customer ledger integrity
                </h3>
                <span class="kv-badge {{ $mismatches->isEmpty() ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                    {{ $mismatches->isEmpty() ? 'All matched' : $mismatches->count() . ' discrepanc' . ($mismatches->count() === 1 ? 'y' : 'ies') }}
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-ink-50/70 border-b border-ink-100">
                        <tr>
                            <th class="kv-th">Customer</th>
                            <th class="kv-th">Actual balance</th>
                            <th class="kv-th">Expected (Σ txns)</th>
                            <th class="kv-th">Diff</th>
                            <th class="kv-th">Deposits</th>
                            <th class="kv-th">Refunds</th>
                            <th class="kv-th">Purchases</th>
                            <th class="kv-th">Adjustments</th>
                            <th class="kv-th">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach($report['customers'] as $c)
                            <tr class="hover:bg-ink-50/60 transition {{ $c['match'] ? '' : 'bg-red-50/40' }}">
                                <td class="kv-td text-xs">
                                    <a href="{{ route('admin.users.show', $c['user']) }}" class="font-semibold text-ink-900 hover:text-brand-600">{{ $c['user']->email ?? '—' }}</a>
                                </td>
                                <td class="kv-td font-bold text-ink-900">{{ xaf($c['actual']) }}</td>
                                <td class="kv-td text-ink-600">{{ xaf($c['expected']) }}</td>
                                <td class="kv-td font-bold {{ $c['match'] ? 'text-ink-400' : 'text-red-600' }}">{{ $c['match'] ? '—' : xaf($c['difference']) }}</td>
                                <td class="kv-td text-xs text-emerald-600">+{{ xaf($c['deposits']) }}</td>
                                <td class="kv-td text-xs text-amber-600">+{{ xaf($c['refunds']) }}</td>
                                <td class="kv-td text-xs text-sky-600">{{ xaf($c['purchases']) }}</td>
                                <td class="kv-td text-xs text-violet-600">{{ xaf($c['adjustments']) }}</td>
                                <td class="kv-td">
                                    @if($c['match'])
                                        <span class="kv-badge bg-emerald-100 text-emerald-700">MATCH</span>
                                    @else
                                        <span class="kv-badge bg-red-100 text-red-700">DISCREPANCY</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Order findings --}}
        @if(!empty($report['orders']['findings']))
            <div class="kv-card overflow-hidden">
                <div class="px-5 py-4 border-b border-ink-100">
                    <h3 class="text-sm font-bold text-ink-900 uppercase tracking-wider">
                        <i class="fas fa-rectangle-list text-ink-400 mr-2"></i>Order findings ({{ count($report['orders']['findings']) }})
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-ink-50/70 border-b border-ink-100">
                            <tr><th class="kv-th">Order</th><th class="kv-th">Issue</th><th class="kv-th">Detail</th><th class="kv-th">Amount</th></tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach($report['orders']['findings'] as $f)
                                <tr class="hover:bg-ink-50/60 transition">
                                    <td class="kv-td"><a href="{{ route('admin.orders.show', $f['order']) }}" class="font-mono text-xs text-brand-600">{{ $f['order']->order_id }}</a></td>
                                    <td class="kv-td"><span class="kv-badge bg-amber-100 text-amber-700">{{ $f['issue'] }}</span></td>
                                    <td class="kv-td text-xs text-ink-600 max-w-md">{{ $f['detail'] }}</td>
                                    <td class="kv-td font-bold text-ink-900">{{ xaf($f['order']->selling_price) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Pending reclassifications + ambiguous records --}}
        <div class="grid lg:grid-cols-2 gap-5">
            <div class="kv-card p-5">
                <h3 class="text-sm font-bold text-ink-900 uppercase tracking-wider mb-3">
                    <i class="fas fa-arrow-right-arrow-left text-ink-400 mr-2"></i>Pending reclassifications ({{ count($tx['proposals']) }})
                </h3>
                @if(empty($tx['proposals']) && empty($report['wallets']) && empty($report['orders']['profit_fixes']))
                    <p class="text-sm text-ink-500">Every transaction is correctly classified.</p>
                @else
                    @if(!empty($tx['proposals']))
                        <ul class="space-y-2 text-xs">
                            @foreach($tx['proposals'] as $p)
                                <li class="flex items-center justify-between gap-3 border-b border-ink-50 pb-2">
                                    <span class="font-mono text-ink-600">{{ $p['transaction']->transaction_id }}</span>
                                    <span>{{ ucfirst($p['from']) }} → <strong>{{ ucfirst($p['to']) }}</strong></span>
                                    <span class="font-bold">{{ xaf($p['transaction']->amount) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if(!empty($report['wallets']) || !empty($report['orders']['profit_fixes']))
                        <p class="mt-2 text-xs text-ink-500">
                            + {{ count($report['wallets']) }} wallet total fix{{ count($report['wallets']) === 1 ? '' : 'es' }},
                            {{ count($report['orders']['profit_fixes']) }} order profit fix{{ count($report['orders']['profit_fixes']) === 1 ? '' : 'es' }}.
                        </p>
                    @endif
                    <form method="POST" action="{{ route('admin.reconciliation.apply') }}" class="mt-3"
                          onsubmit="return confirm('Apply {{ count($tx['proposals']) + count($report['wallets']) + count($report['orders']['profit_fixes']) }} corrections? Every change is audit-logged with before/after values.')">
                        @csrf
                        <button type="submit" class="kv-btn-primary text-xs">
                            <i class="fas fa-wrench"></i> Apply corrections
                        </button>
                    </form>
                    <p class="mt-2 text-xs text-ink-500">Same audited path as <code class="font-mono bg-ink-50 px-1.5 py-0.5 rounded">php artisan wallet:reconcile --apply</code>.</p>
                @endif
            </div>

            <div class="kv-card p-5">
                <h3 class="text-sm font-bold text-ink-900 uppercase tracking-wider mb-3">
                    <i class="fas fa-circle-question text-ink-400 mr-2"></i>Ambiguous records ({{ count($tx['ambiguous']) }})
                </h3>
                @if(empty($tx['ambiguous']))
                    <p class="text-sm text-ink-500">No unclassifiable transactions.</p>
                @else
                    <ul class="space-y-2 text-xs">
                        @foreach($tx['ambiguous'] as $a)
                            <li class="border-b border-ink-50 pb-2">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-mono text-ink-600">{{ $a['transaction']->transaction_id }}</span>
                                    <span class="kv-badge {{ transactionTypeColor($a['transaction']->type) }}">{{ ucfirst($a['transaction']->type) }}</span>
                                    <span class="font-bold">{{ xaf($a['transaction']->amount) }}</span>
                                </div>
                                <p class="text-ink-400 mt-1">{{ $a['reason'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-xs text-ink-500">Never auto-changed — review each against payment/order records and update manually.</p>
                @endif
            </div>
        </div>

    </div>
</x-admin-layout>
