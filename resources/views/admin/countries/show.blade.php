<x-admin-layout>
    <x-slot name="title">{{ $country->name }}</x-slot>
    <x-slot name="header">Country detail</x-slot>

    <div class="max-w-3xl space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.countries.index') }}" class="w-9 h-9 rounded-xl border border-ink-200 bg-white grid place-items-center text-ink-500 hover:text-ink-800 transition">
                <i class="fas fa-arrow-left text-sm"></i>
            </a>
            <a href="{{ route('admin.countries.edit', $country) }}" class="kv-btn-primary !py-2 text-xs ml-auto"><i class="fas fa-pen"></i> Edit</a>
        </div>

        <div class="kv-card p-6 sm:p-8 flex items-center gap-5">
            <span class="text-5xl">{{ countryFlag($country->code) }}</span>
            <div class="flex-1">
                <h1 class="text-xl font-extrabold text-ink-900">{{ $country->name }}</h1>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <span class="kv-badge bg-ink-100 text-ink-700 font-mono">{{ $country->code }}</span>
                    <span class="kv-badge bg-ink-100 text-ink-700 font-mono">{{ $country->dial_code }}</span>
                    <x-status-badge :status="$country->is_active ? 'active' : 'inactive'" />
                </div>
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
            <x-stat-card label="Total orders" :value="$country->orders()->count()" icon="fa-receipt" accent="brand" />
            <x-stat-card label="Completed" :value="$country->orders()->where('status','completed')->count()" icon="fa-circle-check" accent="green" />
            <x-stat-card label="Revenue" :value="xaf($country->orders()->where('status','completed')->sum('selling_price'))" icon="fa-sack-dollar" accent="violet" />
        </div>

        <div class="kv-card overflow-hidden">
            <div class="px-5 py-4 border-b border-ink-100"><h2 class="font-bold text-ink-900 text-sm">Recent orders in {{ $country->name }}</h2></div>
            @php $countryOrders = $country->orders()->with(['user','service'])->latest()->limit(10)->get(); @endphp
            @if($countryOrders->isEmpty())
                <div class="py-8 text-center text-sm text-ink-400">No orders for this country yet.</div>
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach($countryOrders as $order)
                        <li>
                            <a href="{{ route('admin.orders.show', $order) }}" class="flex items-center gap-3 px-5 py-3.5 hover:bg-ink-50 transition">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-ink-900 truncate">{{ $order->service->name ?? '—' }}</p>
                                    <p class="text-xs text-ink-400 font-mono">{{ $order->order_id }} · {{ $order->user->email ?? '—' }}</p>
                                </div>
                                <span class="text-sm font-bold text-ink-900">{{ xaf($order->selling_price) }}</span>
                                <x-status-badge :status="$order->status" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-admin-layout>
