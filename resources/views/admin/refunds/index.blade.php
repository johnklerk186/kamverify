<x-admin-layout>
    <x-slot name="title">Refunds</x-slot>
    <x-slot name="header">Refunds</x-slot>

    <div class="space-y-5">
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <x-stat-card label="Total refunded" :value="xaf($totals['processed'])" icon="fa-rotate-left" accent="amber" />
            <x-stat-card label="Refund count" :value="$totals['count']" icon="fa-receipt" accent="brand" />
            <x-stat-card label="Today" :value="xaf($totals['today'])" icon="fa-calendar-day" accent="blue" />
        </div>

        <form method="GET" action="{{ route('admin.refunds.index') }}" class="kv-card p-4 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 text-xs"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Refund ID, order ID, or email…" class="kv-input !pl-9">
            </div>
            <select name="status" class="kv-input sm:w-44">
                <option value="">All statuses</option>
                @foreach(['pending','processed','failed'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="kv-btn-secondary shrink-0"><i class="fas fa-filter"></i> Filter</button>
        </form>

        <div class="kv-card overflow-hidden">
            @if($refunds->isEmpty())
                <x-empty-state icon="fa-rotate-left" title="No refunds found" message="Order refunds appear here automatically." />
            @else
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-ink-50/70 border-b border-ink-100">
                            <tr>
                                <th class="kv-th">Refund</th>
                                <th class="kv-th">Customer</th>
                                <th class="kv-th">Order</th>
                                <th class="kv-th">Amount</th>
                                <th class="kv-th">Status</th>
                                <th class="kv-th">Reason</th>
                                <th class="kv-th">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach($refunds as $refund)
                                <tr class="hover:bg-ink-50/60 transition">
                                    <td class="kv-td font-mono text-xs font-semibold text-ink-900">{{ $refund->refund_id }}</td>
                                    <td class="kv-td text-xs text-ink-600">{{ $refund->user->email ?? '—' }}</td>
                                    <td class="kv-td">
                                        @if($refund->order)
                                            <a href="{{ route('admin.orders.show', $refund->order) }}" class="font-mono text-xs text-brand-600 hover:text-brand-700">{{ $refund->order->order_id }}</a>
                                        @else — @endif
                                    </td>
                                    <td class="kv-td font-bold text-emerald-600">{{ xaf($refund->amount) }}</td>
                                    <td class="kv-td"><x-status-badge :status="$refund->status" /></td>
                                    <td class="kv-td text-xs text-ink-500 max-w-[14rem] truncate">{{ $refund->reason ?? '—' }}</td>
                                    <td class="kv-td text-xs text-ink-500">{{ $refund->created_at->format('M d, H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <ul class="md:hidden divide-y divide-ink-100">
                    @foreach($refunds as $refund)
                        <li class="px-5 py-4">
                            <div class="flex justify-between items-center gap-3">
                                <span class="font-mono text-xs font-semibold text-ink-900">{{ $refund->refund_id }}</span>
                                <x-status-badge :status="$refund->status" />
                            </div>
                            <p class="mt-1 text-xs text-ink-500">{{ $refund->user->email ?? '—' }}</p>
                            <div class="mt-1 flex justify-between text-xs">
                                <span class="text-ink-400">{{ $refund->created_at->format('M d, H:i') }}</span>
                                <span class="font-bold text-emerald-600">{{ xaf($refund->amount) }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($refunds->hasPages())
            <div>{{ $refunds->links() }}</div>
        @endif
    </div>
</x-admin-layout>
