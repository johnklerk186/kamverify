<x-app-layout>
    <x-slot name="title">Orders</x-slot>

    <div class="space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-ink-900 tracking-tight">Orders</h1>
                <p class="mt-1 text-sm text-ink-500">Your virtual number purchases and their SMS status.</p>
            </div>
            <a href="{{ route('orders.create') }}" class="kv-btn-primary shrink-0"><i class="fas fa-plus"></i> Buy number</a>
        </div>

        {{-- Status tabs --}}
        @php
            $tabs = [
                '' => ['All', $counts['all']],
                'active' => ['Active', $counts['active']],
                'completed' => ['Completed', $counts['completed']],
                'cancelled' => ['Cancelled / Refunded', $counts['cancelled']],
                'expired' => ['Expired', $counts['expired']],
            ];
            $current = $filters['status'] ?? '';
        @endphp
        <div class="flex gap-1.5 overflow-x-auto pb-1 -mx-1 px-1">
            @foreach($tabs as $key => [$label, $count])
                <a href="{{ route('orders.index', array_filter(['status' => $key, 'search' => $filters['search'], 'service_id' => $filters['service_id'], 'country_id' => $filters['country_id']])) }}"
                   class="flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-semibold transition
                          {{ $current === $key ? 'bg-ink-900 text-white' : 'bg-white border border-ink-200/70 text-ink-600 hover:border-ink-300 hover:text-ink-900' }}">
                    {{ $label }}
                    <span class="text-xs {{ $current === $key ? 'text-ink-300' : 'text-ink-400' }}">{{ $count }}</span>
                </a>
            @endforeach
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('orders.index') }}" class="kv-card p-4 flex flex-col sm:flex-row gap-3">
            @if($current)<input type="hidden" name="status" value="{{ $current }}">@endif
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 text-xs"></i>
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search order ID or number…"
                       class="kv-input !pl-9">
            </div>
            <select name="service_id" class="kv-input sm:w-48">
                <option value="">All services</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" @selected($filters['service_id'] == $service->id)>{{ $service->name }}</option>
                @endforeach
            </select>
            <select name="country_id" class="kv-input sm:w-48">
                <option value="">All countries</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}" @selected($filters['country_id'] == $country->id)>{{ $country->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="kv-btn-secondary shrink-0"><i class="fas fa-filter"></i> Filter</button>
        </form>

        {{-- Orders --}}
        <div class="kv-card overflow-hidden">
            @if($orders->isEmpty())
                <x-empty-state icon="fa-receipt" title="No orders found"
                               message="{{ $current || $filters['search'] ? 'Try adjusting your filters.' : 'Purchase your first virtual number to get started.' }}">
                    @if(!$current && !$filters['search'])
                        <a href="{{ route('orders.create') }}" class="kv-btn-primary"><i class="fas fa-plus"></i> Buy a number</a>
                    @endif
                </x-empty-state>
            @else
                {{-- Desktop table --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-ink-50/70 border-b border-ink-100">
                            <tr>
                                <th class="kv-th">Order</th>
                                <th class="kv-th">Service / Country</th>
                                <th class="kv-th">Number</th>
                                <th class="kv-th">Price</th>
                                <th class="kv-th">Status</th>
                                <th class="kv-th">Created</th>
                                <th class="kv-th"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach($orders as $order)
                                <tr class="hover:bg-ink-50/60 transition">
                                    <td class="kv-td font-mono text-xs font-semibold text-ink-900">{{ $order->order_id }}</td>
                                    <td class="kv-td">
                                        <div class="flex items-center gap-2">
                                            @php [$icon, $color] = serviceIcon($order->service->slug); @endphp
                                            <i class="{{ $icon }} {{ $color }}"></i>
                                            <span class="font-medium text-ink-900">{{ $order->service->name }}</span>
                                            <span class="text-ink-300">·</span>
                                            <span>{{ countryFlag($order->country->code) }}</span>
                                            <span class="text-ink-600">{{ $order->country->name }}</span>
                                        </div>
                                    </td>
                                    <td class="kv-td font-mono text-xs">{{ $order->phone_number ?? '—' }}</td>
                                    <td class="kv-td font-semibold text-ink-900">{{ xaf($order->selling_price) }}</td>
                                    <td class="kv-td"><x-status-badge :status="$order->status" /></td>
                                    <td class="kv-td text-xs text-ink-500">{{ $order->created_at->format('M d, H:i') }}</td>
                                    <td class="kv-td text-right">
                                        <a href="{{ route('orders.show', $order) }}" class="text-brand-600 hover:text-brand-700 font-semibold text-sm">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile cards --}}
                <ul class="md:hidden divide-y divide-ink-100">
                    @foreach($orders as $order)
                        <li>
                            <a href="{{ route('orders.show', $order) }}" class="block px-5 py-4 active:bg-ink-50">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="text-lg shrink-0">{{ countryFlag($order->country->code) }}</span>
                                        <span class="font-semibold text-sm text-ink-900 truncate min-w-0">{{ $order->service->name }}</span>
                                        <span class="text-xs text-ink-400 truncate min-w-0 hidden sm:inline">{{ $order->country->name }}</span>
                                    </div>
                                    <x-status-badge :status="$order->status" />
                                </div>
                                <div class="mt-2 flex items-center justify-between text-xs">
                                    <span class="font-mono text-ink-500">{{ $order->phone_number ?? $order->order_id }}</span>
                                    <span class="font-semibold text-ink-900">{{ xaf($order->selling_price) }}</span>
                                </div>
                                <p class="mt-1 text-[11px] text-ink-400">{{ $order->created_at->format('M d, Y H:i') }}</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($orders->hasPages())
            <div>{{ $orders->links() }}</div>
        @endif
    </div>
</x-app-layout>
