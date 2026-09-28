<x-admin-layout>
    <x-slot name="title">Orders</x-slot>
    <x-slot name="header">Orders</x-slot>

    <div class="space-y-5">
        {{-- Status tabs --}}
        @php
            $statuses = ['' => 'All', 'waiting_for_sms' => 'Waiting', 'sms_received' => 'SMS received', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded', 'expired' => 'Expired', 'failed' => 'Failed'];
            $current = request('status', '');
        @endphp
        <div class="flex gap-1.5 overflow-x-auto pb-1 -mx-1 px-1">
            @foreach($statuses as $key => $label)
                <a href="{{ route('admin.orders.index', array_merge(request()->except('status', 'page'), $key ? ['status' => $key] : [])) }}"
                   class="flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-semibold transition
                          {{ $current === $key ? 'bg-ink-900 text-white' : 'bg-white border border-ink-200/70 text-ink-600 hover:border-ink-300' }}">
                    {{ $label }}
                    @if($key && isset($statusCounts[$key]))
                        <span class="text-xs {{ $current === $key ? 'text-ink-300' : 'text-ink-400' }}">{{ $statusCounts[$key] }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.orders.index') }}" class="kv-card p-4 flex flex-col sm:flex-row gap-3">
            @if($current)<input type="hidden" name="status" value="{{ $current }}">@endif
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 text-xs"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Order ID, number, or customer…" class="kv-input !pl-9">
            </div>
            <select name="service_id" class="kv-input sm:w-44">
                <option value="">All services</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" @selected(request('service_id') == $service->id)>{{ $service->name }}</option>
                @endforeach
            </select>
            <select name="country_id" class="kv-input sm:w-44">
                <option value="">All countries</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}" @selected(request('country_id') == $country->id)>{{ $country->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="kv-btn-secondary shrink-0"><i class="fas fa-filter"></i> Filter</button>
        </form>

        <div class="kv-card overflow-hidden">
            @if($orders->isEmpty())
                <x-empty-state icon="fa-receipt" title="No orders found" />
            @else
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-ink-50/70 border-b border-ink-100">
                            <tr>
                                <th class="kv-th">Order</th>
                                <th class="kv-th">Customer</th>
                                <th class="kv-th">Service / Country</th>
                                <th class="kv-th">Number</th>
                                <th class="kv-th">Price</th>
                                <th class="kv-th">Profit</th>
                                <th class="kv-th">Status</th>
                                <th class="kv-th">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach($orders as $order)
                                <tr class="hover:bg-ink-50/60 transition cursor-pointer" onclick="window.location='{{ route('admin.orders.show', $order) }}'">
                                    <td class="kv-td font-mono text-xs font-semibold text-ink-900">{{ $order->order_id }}</td>
                                    <td class="kv-td">
                                        <p class="text-xs font-semibold text-ink-900">{{ $order->user->name ?? '—' }}</p>
                                        <p class="text-[11px] text-ink-400">{{ $order->user->email ?? '' }}</p>
                                    </td>
                                    <td class="kv-td">
                                        <div class="flex items-center gap-2 text-xs">
                                            @php [$icon, $color] = serviceIcon($order->service->slug ?? ''); @endphp
                                            <i class="{{ $icon }} {{ $color }}"></i>
                                            <span class="font-medium text-ink-900">{{ $order->service->name ?? '—' }}</span>
                                            <span>{{ countryFlag($order->country->code ?? null) }}</span>
                                        </div>
                                    </td>
                                    <td class="kv-td font-mono text-xs">{{ $order->phone_number ?? '—' }}</td>
                                    <td class="kv-td font-semibold text-ink-900">{{ xaf($order->selling_price) }}</td>
                                    <td class="kv-td text-emerald-600 font-semibold">{{ xaf($order->profit) }}</td>
                                    <td class="kv-td"><x-status-badge :status="$order->status" /></td>
                                    <td class="kv-td text-xs text-ink-500">{{ $order->created_at->format('M d, H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <ul class="lg:hidden divide-y divide-ink-100">
                    @foreach($orders as $order)
                        <li>
                            <a href="{{ route('admin.orders.show', $order) }}" class="block px-5 py-4 active:bg-ink-50">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-mono text-xs font-semibold text-ink-900">{{ $order->order_id }}</span>
                                    <x-status-badge :status="$order->status" />
                                </div>
                                <p class="mt-1.5 text-xs text-ink-600">{{ $order->service->name ?? '—' }} · {{ $order->country->name ?? '—' }} · {{ $order->user->email ?? '—' }}</p>
                                <div class="mt-1 flex justify-between text-xs">
                                    <span class="font-mono text-ink-400">{{ $order->phone_number ?? '—' }}</span>
                                    <span class="font-bold text-ink-900">{{ xaf($order->selling_price) }}</span>
                                </div>
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
</x-admin-layout>
