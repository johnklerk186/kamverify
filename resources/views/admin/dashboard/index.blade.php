<x-admin-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="header">Admin dashboard</x-slot>

    <div class="space-y-6">
        {{-- Today --}}
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-ink-500">Today</h2>
                <a href="{{ route('admin.analytics.index', ['range' => 'today']) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Analytics <i class="fas fa-arrow-right text-[10px]"></i></a>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <x-stat-card label="Sales today" :value="xaf($stats['today_sales'])" icon="fa-sack-dollar" accent="green" />
                <x-stat-card label="Orders today" :value="number_format($stats['today_orders'])" icon="fa-receipt" accent="brand" />
                <x-stat-card label="Deposits today" :value="xaf($stats['today_deposits'])" icon="fa-arrow-down" accent="blue" />
                <x-stat-card label="New customers" :value="number_format($stats['today_customers'])" icon="fa-user-plus" accent="violet" />
                <x-stat-card label="Refunds today" :value="xaf($stats['today_refunds'])" icon="fa-rotate-left" accent="amber" />
            </div>
        </div>

        {{-- All-time --}}
        <div>
            <h2 class="text-xs font-bold uppercase tracking-wider text-ink-500 mb-3">All time</h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <x-stat-card label="Customers" :value="number_format($stats['total_customers'])" icon="fa-users" accent="brand" :href="route('admin.users.index')" :sub="$stats['suspended'].' suspended'" />
                <x-stat-card label="Active orders" :value="number_format($stats['active_orders'])" icon="fa-bolt" accent="amber" :href="route('admin.orders.index', ['status' => 'waiting_for_sms'])" />
                <x-stat-card label="Gross sales" :value="xaf($stats['total_sales'])" icon="fa-sack-dollar" accent="green" sub="Paid, non-refunded orders" />
                <x-stat-card label="Gross profit" :value="xaf($stats['gross_profit'])" icon="fa-chart-line" accent="violet" :sub="$stats['margin'].'% margin'" />
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mt-4">
                <x-stat-card label="Total orders" :value="number_format($stats['total_orders'])" icon="fa-receipt" accent="brand" :href="route('admin.orders.index')" />
                <x-stat-card label="Completed" :value="number_format($stats['completed_orders'])" icon="fa-circle-check" accent="green" />
                <x-stat-card label="Cancelled" :value="number_format($stats['cancelled_orders'])" icon="fa-circle-xmark" accent="red" />
                <x-stat-card label="Provider cost" :value="xaf($stats['provider_cost'])" icon="fa-server" accent="ink" sub="All-time COGS" />
                <x-stat-card label="Deposits" :value="xaf($stats['total_deposits'])" icon="fa-arrow-down" accent="blue" :href="route('admin.transactions.index', ['type' => 'deposit'])" :sub="xaf($stats['total_refunds']).' refunded'" />
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6 items-start">
            {{-- Recent orders --}}
            <div class="lg:col-span-2 kv-card overflow-hidden">
                <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-ink-100">
                    <h2 class="font-bold text-ink-900 text-sm">Recent orders</h2>
                    <a href="{{ route('admin.orders.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">View all</a>
                </div>
                @if($recentOrders->isEmpty())
                    <x-empty-state icon="fa-receipt" title="No orders yet" />
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach($recentOrders as $order)
                            <li>
                                <a href="{{ route('admin.orders.show', $order) }}" class="flex items-center gap-3.5 px-5 sm:px-6 py-3.5 hover:bg-ink-50 transition">
                                    <span class="text-lg">{{ countryFlag($order->country->code ?? null) }}</span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-ink-900 truncate">
                                            {{ $order->service->name ?? '—' }} · {{ $order->country->name ?? '—' }}
                                        </p>
                                        <p class="text-xs text-ink-400 truncate">{{ $order->user->email ?? '—' }} · {{ $order->order_id }}</p>
                                    </div>
                                    <span class="text-sm font-semibold text-ink-900">{{ xaf($order->selling_price) }}</span>
                                    <x-status-badge :status="$order->status" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="space-y-6">
                {{-- Recent transactions --}}
                <div class="kv-card overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-ink-100">
                        <h2 class="font-bold text-ink-900 text-sm">Transactions</h2>
                        <a href="{{ route('admin.transactions.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">All</a>
                    </div>
                    @if($recentTransactions->isEmpty())
                        <div class="py-8 text-center text-sm text-ink-400">No transactions yet</div>
                    @else
                        <ul class="divide-y divide-ink-100">
                            @foreach($recentTransactions->take(6) as $tx)
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <span class="w-8 h-8 rounded-lg grid place-items-center {{ transactionTypeColor($tx->type) }}">
                                        <i class="fas {{ in_array($tx->type, ['deposit','refund','reward']) ? 'fa-arrow-down' : 'fa-arrow-up' }} text-xs"></i>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-ink-800 truncate">{{ $tx->user->email ?? '—' }}</p>
                                        <p class="text-[11px] text-ink-400">{{ ucfirst($tx->type) }} · {{ $tx->created_at->diffForHumans() }}</p>
                                    </div>
                                    <span class="text-xs font-bold text-ink-900">{{ xaf($tx->amount) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- New customers --}}
                <div class="kv-card overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-ink-100">
                        <h2 class="font-bold text-ink-900 text-sm">New customers</h2>
                        <a href="{{ route('admin.users.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">All</a>
                    </div>
                    @if($recentUsers->isEmpty())
                        <div class="py-8 text-center text-sm text-ink-400">No customers yet</div>
                    @else
                        <ul class="divide-y divide-ink-100">
                            @foreach($recentUsers->take(5) as $u)
                                <li>
                                    <a href="{{ route('admin.users.show', $u) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-ink-50 transition">
                                        <span class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 grid place-items-center text-xs font-bold">
                                            {{ strtoupper(substr($u->name, 0, 1)) }}
                                        </span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-semibold text-ink-800 truncate">{{ $u->name }}</p>
                                            <p class="text-[11px] text-ink-400 truncate">{{ $u->email }}</p>
                                        </div>
                                        <span class="text-[11px] text-ink-400">{{ $u->created_at->diffForHumans() }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
