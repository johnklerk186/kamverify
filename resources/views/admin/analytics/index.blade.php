<x-admin-layout>
    <x-slot name="title">Analytics</x-slot>
    <x-slot name="header">Sales analytics</x-slot>

    @php
        $ranges = [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'last7' => 'Last 7 days',
            'last30' => 'Last 30 days',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'custom' => 'Custom',
        ];
        $granularities = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
        $baseParams = ['range' => $range, 'from' => $from->toDateString(), 'to' => $to->toDateString()];
    @endphp

    <div class="space-y-6"
         x-data="salesChart({
             labels: @js($series['labels']),
             revenue: @js($series['revenue']),
             orders: @js($series['orders']),
             endpoint: '{{ route('admin.analytics.sales-data') }}',
             range: '{{ $range }}',
             granularity: '{{ $granularity }}',
             from: '{{ $from->toDateString() }}',
             to: '{{ $to->toDateString() }}',
         })">

        {{-- Controls --}}
        <div class="kv-card p-4 sm:p-5">
            <form method="GET" action="{{ route('admin.analytics.index') }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-0">
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-ink-500 mb-1.5">Range</label>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($ranges as $key => $label)
                            <button type="submit" name="range" value="{{ $key }}"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $range === $key ? 'bg-ink-900 text-white' : 'bg-ink-100 text-ink-600 hover:bg-ink-200' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="flex items-end gap-2 {{ $range === 'custom' ? '' : 'hidden sm:flex sm:opacity-60' }}">
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wide text-ink-500 mb-1.5">From</label>
                        <input type="date" name="from" value="{{ $from->toDateString() }}" class="kv-input !py-1.5 text-xs" {{ $range === 'custom' ? 'onchange=this.form.submit()' : 'disabled' }}>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wide text-ink-500 mb-1.5">To</label>
                        <input type="date" name="to" value="{{ $to->toDateString() }}" class="kv-input !py-1.5 text-xs" {{ $range === 'custom' ? 'onchange=this.form.submit()' : 'disabled' }}>
                    </div>
                    @if($range === 'custom')
                        <button type="submit" class="kv-btn-secondary !py-1.5 text-xs">Apply</button>
                    @endif
                </div>
                <input type="hidden" name="granularity" :value="granularity">
                <div class="ml-auto">
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-ink-500 mb-1.5">Group by</label>
                    <div class="flex rounded-lg bg-ink-100 p-1 gap-1">
                        @foreach($granularities as $key => $label)
                            <button type="button" @click="setGranularity('{{ $key }}')"
                                    :class="granularity === '{{ $key }}' ? 'bg-white text-ink-900 shadow-sm' : 'text-ink-500 hover:text-ink-800'"
                                    class="px-3 py-1.5 rounded-md text-xs font-semibold transition">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </form>
        </div>

        {{-- Sales chart --}}
        <div class="kv-card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-ink-100">
                <div>
                    <h2 class="font-bold text-ink-900 text-sm">Sales revenue</h2>
                    <p class="text-xs text-ink-400 mt-0.5">{{ $from->format('M j, Y') }} — {{ $to->format('M j, Y') }}</p>
                </div>
                <div class="flex items-center gap-4 text-right">
                    <div>
                        <p class="text-[11px] font-medium text-ink-500 uppercase">Revenue</p>
                        <p class="text-lg font-bold text-ink-900" x-text="Math.round(totalRevenue()).toLocaleString() + ' XAF'"></p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium text-ink-500 uppercase">Orders</p>
                        <p class="text-lg font-bold text-ink-900" x-text="totalOrders()"></p>
                    </div>
                </div>
            </div>

            <div class="px-3 sm:px-5 py-5">
                <template x-if="loading">
                    <div class="h-56 grid place-items-center text-sm text-ink-400">
                        <i class="fas fa-circle-notch fa-spin mr-2"></i> Loading chart…
                    </div>
                </template>
                <template x-if="!loading && revenue.length === 0">
                    <x-empty-state icon="fa-chart-line" title="No sales in this range" />
                </template>
                <div x-show="!loading && revenue.length > 0" class="overflow-x-auto">
                    <svg :viewBox="'0 0 ' + Math.max(labels.length * barWidth, 320) + ' 220'"
                         :style="'min-width:' + Math.max(labels.length * barWidth, 320) + 'px'"
                         class="w-full h-56" role="img" aria-label="Sales revenue chart">
                        <template x-for="(v, i) in revenue" :key="i">
                            <g>
                                <rect :x="i * barWidth + 6" :y="barY(v)" :width="barWidth - 12" :height="barH(v)"
                                      rx="4" class="fill-brand-500 hover:fill-brand-600 transition">
                                    <title x-text="labels[i] + ': ' + Math.round(v).toLocaleString() + ' XAF · ' + orders[i] + ' orders'"></title>
                                </rect>
                                <text :x="i * barWidth + barWidth / 2" y="212" text-anchor="middle"
                                      class="fill-ink-400" style="font-size:9px"
                                      x-text="labelEvery(i) ? labels[i] : ''"></text>
                            </g>
                        </template>
                        <line x1="0" y1="190" :x2="Math.max(labels.length * barWidth, 320)" y2="190" class="stroke-ink-200" stroke-width="1"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Orders by status --}}
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Total orders" :value="number_format($business['orders']['total'])" icon="fa-receipt" accent="brand" :href="route('admin.orders.index')" />
            <x-stat-card label="Completed" :value="number_format($business['orders']['completed'])" icon="fa-circle-check" accent="green" />
            <x-stat-card label="Waiting / assigned" :value="number_format($business['orders']['waiting'])" icon="fa-clock" accent="amber" />
            <x-stat-card label="Processing" :value="number_format($business['orders']['processing'])" icon="fa-spinner" accent="blue" />
            <x-stat-card label="Cancelled" :value="number_format($business['orders']['cancelled'])" icon="fa-circle-xmark" accent="red" />
            <x-stat-card label="Expired" :value="number_format($business['orders']['expired'])" icon="fa-hourglass-end" accent="ink" />
            <x-stat-card label="Failed" :value="number_format($business['orders']['failed'])" icon="fa-triangle-exclamation" accent="red" />
            <x-stat-card label="Refunded" :value="number_format($business['orders']['refunded'])" icon="fa-rotate-left" accent="amber" />
        </div>

        {{-- Revenue + profit + customers --}}
        <div class="grid lg:grid-cols-3 gap-6 items-start">
            <div class="kv-card p-5 sm:p-6">
                <h2 class="font-bold text-ink-900 text-sm mb-4">Revenue</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Gross sales</dt><dd class="font-bold text-ink-900">{{ xaf($business['revenue']['gross_sales']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Wallet deposits</dt><dd class="font-semibold text-ink-800">{{ xaf($business['revenue']['deposits']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Payments received</dt><dd class="font-semibold text-ink-800">{{ xaf($business['revenue']['payments']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Refunds issued</dt><dd class="font-semibold text-red-600">-{{ xaf($business['revenue']['refunds']) }}</dd></div>
                    <div class="flex justify-between gap-3 pt-3 border-t border-ink-100"><dt class="font-semibold text-ink-900">Net sales</dt><dd class="font-bold text-emerald-600">{{ xaf($business['revenue']['net_sales']) }}</dd></div>
                </dl>
            </div>

            <div class="kv-card p-5 sm:p-6">
                <h2 class="font-bold text-ink-900 text-sm mb-4">Profit</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Customer price</dt><dd class="font-semibold text-ink-800">{{ xaf($business['profit']['customer_price']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Provider cost</dt><dd class="font-semibold text-ink-800">-{{ xaf($business['profit']['provider_cost']) }}</dd></div>
                    <div class="flex justify-between gap-3 pt-3 border-t border-ink-100"><dt class="font-semibold text-ink-900">Gross profit</dt><dd class="font-bold text-emerald-600">{{ xaf($business['profit']['gross_profit']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Profit margin</dt>
                        <dd class="font-bold text-ink-900">{{ $business['profit']['margin'] }}%</dd>
                    </div>
                    <div class="h-2 rounded-full bg-ink-100 overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ min(100, max(0, $business['profit']['margin'])) }}%"></div>
                    </div>
                </dl>
            </div>

            <div class="kv-card p-5 sm:p-6">
                <h2 class="font-bold text-ink-900 text-sm mb-4">Customers</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Total customers</dt><dd class="font-bold text-ink-900">{{ number_format($business['customers']['total']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">New in range</dt><dd class="font-semibold text-emerald-600">+{{ number_format($business['customers']['new']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Ordered in range</dt><dd class="font-semibold text-ink-800">{{ number_format($business['customers']['active']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Returning buyers</dt><dd class="font-semibold text-ink-800">{{ number_format($business['customers']['returning']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Suspended</dt><dd class="font-semibold text-red-600">{{ number_format($business['customers']['suspended']) }}</dd></div>
                </dl>
            </div>
        </div>

        {{-- Top services + countries --}}
        <div class="grid lg:grid-cols-2 gap-6 items-start">
            <div class="kv-card overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-ink-100">
                    <h2 class="font-bold text-ink-900 text-sm">Top services</h2>
                </div>
                @if($business['top_services']->isEmpty())
                    <x-empty-state icon="fa-mobile-screen" title="No sales in this range" />
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach($business['top_services'] as $s)
                            <li class="flex items-center gap-3 px-5 sm:px-6 py-3">
                                @php [$icon, $color] = serviceIcon($s->icon ?? null); @endphp
                                <span class="w-8 h-8 rounded-lg bg-ink-50 grid place-items-center shrink-0"><i class="{{ $icon }} {{ $color }}"></i></span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-ink-900 truncate">{{ $s->name }}</p>
                                    <p class="text-xs text-ink-400">{{ number_format($s->orders) }} orders · {{ xaf($s->profit) }} profit</p>
                                </div>
                                <span class="text-sm font-bold text-ink-900 shrink-0">{{ xaf($s->revenue) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="kv-card overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-ink-100">
                    <h2 class="font-bold text-ink-900 text-sm">Top countries</h2>
                </div>
                @if($business['top_countries']->isEmpty())
                    <x-empty-state icon="fa-earth-americas" title="No orders in this range" />
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach($business['top_countries'] as $c)
                            <li class="flex items-center gap-3 px-5 sm:px-6 py-3">
                                <span class="text-lg shrink-0">{{ countryFlag($c->code) }}</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-ink-900 truncate">{{ $c->name }}</p>
                                    <p class="text-xs text-ink-400">{{ number_format($c->orders) }} orders · {{ $c->success_rate }}% completed</p>
                                </div>
                                <span class="text-sm font-bold text-ink-900 shrink-0">{{ xaf($c->revenue) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <script>
        function salesChart(cfg) {
            return {
                labels: cfg.labels,
                revenue: cfg.revenue,
                orders: cfg.orders,
                endpoint: cfg.endpoint,
                range: cfg.range,
                granularity: cfg.granularity,
                from: cfg.from,
                to: cfg.to,
                loading: false,
                barWidth: 48,
                maxVal() { return Math.max(...this.revenue, 0.01); },
                barY(v) { return 190 - (v / this.maxVal()) * 170; },
                barH(v) { return Math.max((v / this.maxVal()) * 170, v > 0 ? 3 : 0); },
                labelEvery(i) { return this.labels.length <= 15 || i % Math.ceil(this.labels.length / 15) === 0; },
                totalRevenue() { return this.revenue.reduce((a, b) => a + b, 0); },
                totalOrders() { return this.orders.reduce((a, b) => a + b, 0); },
                async setGranularity(g) {
                    this.granularity = g;
                    this.loading = true;
                    try {
                        const url = new URL(this.endpoint, window.location.origin);
                        url.searchParams.set('range', this.range);
                        url.searchParams.set('granularity', g);
                        if (this.range === 'custom') {
                            url.searchParams.set('from', this.from);
                            url.searchParams.set('to', this.to);
                        }
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const data = await res.json();
                        this.labels = data.labels;
                        this.revenue = data.revenue;
                        this.orders = data.orders;
                    } finally {
                        this.loading = false;
                    }
                }
            };
        }
    </script>
</x-admin-layout>
