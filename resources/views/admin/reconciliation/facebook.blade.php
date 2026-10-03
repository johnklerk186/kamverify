<x-admin-layout>
    <x-slot name="title">Facebook audit</x-slot>
    <x-slot name="header">Facebook/Meta compatibility audit</x-slot>

    @php
        $t = $report['totals'];
        $statusColors = [
            'completed' => 'text-emerald-700 bg-emerald-50 border-emerald-200',
            'sms_received' => 'text-emerald-700 bg-emerald-50 border-emerald-200',
            'cancelled' => 'text-ink-600 bg-ink-50 border-ink-200',
            'expired' => 'text-amber-700 bg-amber-50 border-amber-200',
            'failed' => 'text-red-700 bg-red-50 border-red-200',
            'refunded' => 'text-blue-700 bg-blue-50 border-blue-200',
        ];
    @endphp

    <div class="space-y-5">

        {{-- What this is, and what it can't see --}}
        <div class="rounded-xl bg-blue-50 border border-blue-200 p-4 text-sm text-blue-900">
            <p class="font-semibold mb-1"><i class="fas fa-circle-info mr-1"></i>How to read this report</p>
            <p>Facebook rejects incompatible numbers <strong>inside its own UI</strong> ("Mobile carrier not supported") — that error never reaches our server.
            Our measurable proxy is an order where an activation was sold but <strong>no SMS was ever received</strong> before the order ended.
            HeroSMS exposes no carrier/operator metadata on our endpoints, so the <strong>number prefix is the only carrier signal we hold</strong>.
            Buckets under {{ $report['prefixes']->where('low_confidence', false)->count() > 0 ? '5 orders' : '—' }} are marked <em>low confidence</em> — do not treat them as proof.</p>
            <form method="GET" class="mt-3 flex items-center gap-2">
                <label class="text-xs">Window:</label>
                <select name="days" class="rounded-lg border-blue-300 text-sm py-1">
                    <option value="" {{ !$days ? 'selected' : '' }}>All history</option>
                    @foreach([7, 30, 90] as $d)
                        <option value="{{ $d }}" {{ $days === $d ? 'selected' : '' }}>Last {{ $d }} days</option>
                    @endforeach
                </select>
                <button class="px-3 py-1 rounded-lg bg-blue-600 text-white text-xs font-semibold">Apply</button>
            </form>
        </div>

        {{-- Headline metrics --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Facebook orders" :value="$t['orders']" icon="fa-brands fa-facebook" accent="brand"
                :sub="$t['active'] . ' still active'" />
            <x-stat-card label="SMS delivered" :value="$t['success_rate'] . '%'" icon="fa-message" accent="green"
                :sub="$t['completed'] . ' of ' . $t['orders'] . ' got a code'" />
            <x-stat-card label="No SMS received" :value="$t['failed_no_sms']" icon="fa-message-slash" accent="red"
                sub="Ended with no code — incl. carrier rejections" />
            <x-stat-card label="Customer refunds" :value="xaf($t['refunds_xaf'])" icon="fa-rotate-left" accent="amber" />
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="HeroSMS cost" :value="xaf($t['provider_cost_xaf'])" icon="fa-server" accent="ink"
                :sub="'$' . number_format($t['provider_cost_usd'], 2) . ' USD'" />
            <x-stat-card label="Cost recovered" :value="xaf(usdToXaf($t['cost_recovered_usd']))" icon="fa-circle-check" accent="green"
                :sub="'$' . number_format($t['cost_recovered_usd'], 2) . ' released/resolved'" />
            <x-stat-card label="KamVerify loss" :value="xaf(usdToXaf($t['kamverify_loss_usd']))" icon="fa-fire" accent="red"
                :sub="'$' . number_format($t['kamverify_loss_usd'], 2) . ' consumed by provider'" />
            <x-stat-card label="Unverified cost" :value="xaf(usdToXaf($t['cost_unverified_usd']))" icon="fa-circle-question" accent="ink"
                :sub="'$' . number_format($t['cost_unverified_usd'], 2) . ' no outcome recorded'" />
        </div>

        {{-- Prefix pattern — the core question --}}
        <div class="kv-card p-5">
            <h3 class="text-sm font-bold text-ink-900 uppercase tracking-wider mb-1">
                <i class="fas fa-phone text-ink-400 mr-2"></i>Failure rate by number prefix
            </h3>
            <p class="text-xs text-ink-500 mb-4">First 4 digits of the digits-only number. A prefix at ~0% success with meaningful volume is a carrier-incompatibility signal — check these against the successful orders before acting.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-ink-500 border-b border-ink-100">
                        <th class="py-2 pr-4">Prefix</th><th class="py-2 pr-4">Orders</th>
                        <th class="py-2 pr-4">SMS received</th><th class="py-2 pr-4">No SMS</th>
                        <th class="py-2 pr-4">Success rate</th><th class="py-2">Confidence</th>
                    </tr></thead>
                    <tbody>
                        @forelse($report['prefixes'] as $p)
                            <tr class="border-b border-ink-50">
                                <td class="py-2 pr-4 font-mono font-semibold text-ink-900">+{{ $p['prefix'] }}…</td>
                                <td class="py-2 pr-4">{{ $p['total'] }}</td>
                                <td class="py-2 pr-4 text-emerald-700">{{ $p['succeeded'] }}</td>
                                <td class="py-2 pr-4 text-red-600">{{ $p['failed_no_sms'] }}</td>
                                <td class="py-2 pr-4">
                                    <span class="font-semibold {{ $p['success_rate'] >= 60 ? 'text-emerald-600' : ($p['success_rate'] > 0 ? 'text-amber-600' : 'text-red-600') }}">{{ $p['success_rate'] }}%</span>
                                </td>
                                <td class="py-2 text-xs">
                                    @if($p['low_confidence'])
                                        <span class="text-ink-400">low — only {{ $p['total'] }} orders</span>
                                    @else
                                        <span class="text-emerald-600 font-medium">adequate sample</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-ink-400">No Facebook orders in this window.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Country pattern --}}
        <div class="kv-card p-5">
            <h3 class="text-sm font-bold text-ink-900 uppercase tracking-wider mb-4">
                <i class="fas fa-globe text-ink-400 mr-2"></i>By country
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-ink-500 border-b border-ink-100">
                        <th class="py-2 pr-4">Country</th><th class="py-2 pr-4">Orders</th>
                        <th class="py-2 pr-4">SMS received</th><th class="py-2 pr-4">No SMS</th><th class="py-2">Success rate</th>
                    </tr></thead>
                    <tbody>
                        @forelse($report['countries'] as $c)
                            <tr class="border-b border-ink-50">
                                <td class="py-2 pr-4 font-medium text-ink-900">{{ $c['country'] }}</td>
                                <td class="py-2 pr-4">{{ $c['total'] }}</td>
                                <td class="py-2 pr-4 text-emerald-700">{{ $c['succeeded'] }}</td>
                                <td class="py-2 pr-4 text-red-600">{{ $c['failed_no_sms'] }}</td>
                                <td class="py-2 font-semibold {{ $c['success_rate'] >= 60 ? 'text-emerald-600' : ($c['success_rate'] > 0 ? 'text-amber-600' : 'text-red-600') }}">{{ $c['success_rate'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-ink-400">No data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Order-level detail --}}
        <div class="kv-card p-5">
            <h3 class="text-sm font-bold text-ink-900 uppercase tracking-wider mb-4">
                <i class="fas fa-list text-ink-400 mr-2"></i>Every Facebook order
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-ink-500 border-b border-ink-100">
                        <th class="py-2 pr-3">#</th><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Country</th>
                        <th class="py-2 pr-3">Number</th><th class="py-2 pr-3">Activation</th><th class="py-2 pr-3">Status</th>
                        <th class="py-2 pr-3">SMS</th><th class="py-2 pr-3">Sold</th><th class="py-2 pr-3">Refund</th>
                        <th class="py-2 pr-3">Provider outcome</th><th class="py-2">Reason</th>
                    </tr></thead>
                    <tbody>
                        @forelse($report['orders'] as $r)
                            <tr class="border-b border-ink-50 align-top">
                                <td class="py-2 pr-3"><a href="{{ route('admin.orders.show', $r['order']) }}" class="text-brand-600 hover:underline">#{{ $r['order']->id }}</a></td>
                                <td class="py-2 pr-3 text-xs text-ink-500 whitespace-nowrap">{{ $r['created_at']->format('M d H:i') }}</td>
                                <td class="py-2 pr-3 text-xs">{{ $r['country'] }}</td>
                                <td class="py-2 pr-3 font-mono text-xs">+{{ $r['masked_phone'] }}</td>
                                <td class="py-2 pr-3 font-mono text-xs text-ink-500">{{ $r['activation_id'] ?: '—' }}</td>
                                <td class="py-2 pr-3"><span class="text-xs px-1.5 py-0.5 rounded border {{ $statusColors[$r['status']] ?? 'text-ink-600 bg-ink-50 border-ink-200' }}">{{ $r['status'] }}</span></td>
                                <td class="py-2 pr-3">{!! $r['sms_received'] ? '<i class="fas fa-check text-emerald-600"></i>' : '<i class="fas fa-xmark text-red-400"></i>' !!}</td>
                                <td class="py-2 pr-3 text-xs">{{ xaf($r['selling_price']) }}</td>
                                <td class="py-2 pr-3 text-xs">{{ $r['refund_amount'] > 0 ? xaf($r['refund_amount']) : '—' }}</td>
                                <td class="py-2 pr-3 text-xs">{{ $r['provider_refund_status'] ?: '—' }}</td>
                                <td class="py-2 text-xs text-ink-500">{{ $r['cancellation_reason'] ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="py-6 text-center text-ink-400">No Facebook orders found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-admin-layout>
