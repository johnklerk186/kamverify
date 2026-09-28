<x-admin-layout>
    <x-slot name="title">Payments</x-slot>
    <x-slot name="header">Payments</x-slot>

    <div class="space-y-5">
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <x-stat-card label="Completed" :value="xaf($totals['completed'])" icon="fa-circle-check" accent="green" />
            <x-stat-card label="Pending" :value="xaf($totals['pending'])" icon="fa-clock" accent="amber" />
            <x-stat-card label="Failed" :value="$totals['failed']" icon="fa-circle-xmark" accent="red" />
        </div>

        <form method="GET" action="{{ route('admin.payments.index') }}" class="kv-card p-4 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 text-xs"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Payment ID or customer email…" class="kv-input !pl-9">
            </div>
            <select name="provider" class="kv-input sm:w-40">
                <option value="">All providers</option>
                @foreach($providers as $p)
                    <option value="{{ $p }}" @selected(request('provider') === $p)>{{ ucfirst($p) }}</option>
                @endforeach
            </select>
            <select name="status" class="kv-input sm:w-40">
                <option value="">All statuses</option>
                @foreach(['pending','completed','failed','cancelled'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="kv-btn-secondary shrink-0"><i class="fas fa-filter"></i> Filter</button>
        </form>

        <div class="kv-card overflow-hidden">
            @if($payments->isEmpty())
                <x-empty-state icon="fa-credit-card" title="No payments found" />
            @else
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-ink-50/70 border-b border-ink-100">
                            <tr>
                                <th class="kv-th">Payment</th>
                                <th class="kv-th">Customer</th>
                                <th class="kv-th">Provider</th>
                                <th class="kv-th">Amount</th>
                                <th class="kv-th">Status</th>
                                <th class="kv-th">Date</th>
                                <th class="kv-th"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach($payments as $payment)
                                <tr class="hover:bg-ink-50/60 transition">
                                    <td class="kv-td font-mono text-xs font-semibold text-ink-900">{{ $payment->payment_id }}</td>
                                    <td class="kv-td text-xs text-ink-600">{{ $payment->user->email ?? '—' }}</td>
                                    <td class="kv-td"><span class="kv-badge bg-ink-100 text-ink-700">{{ ucfirst($payment->provider) }}</span></td>
                                    <td class="kv-td font-bold text-ink-900">{{ xaf($payment->amount) }}</td>
                                    <td class="kv-td"><x-status-badge :status="$payment->status" /></td>
                                    <td class="kv-td text-xs text-ink-500">{{ $payment->created_at->format('M d, H:i') }}</td>
                                    <td class="kv-td text-right"><a href="{{ route('admin.payments.show', $payment) }}" class="text-brand-600 hover:text-brand-700 font-semibold text-sm">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <ul class="md:hidden divide-y divide-ink-100">
                    @foreach($payments as $payment)
                        <li>
                            <a href="{{ route('admin.payments.show', $payment) }}" class="block px-5 py-4 active:bg-ink-50">
                                <div class="flex justify-between items-center gap-3">
                                    <span class="font-mono text-xs font-semibold text-ink-900">{{ $payment->payment_id }}</span>
                                    <x-status-badge :status="$payment->status" />
                                </div>
                                <p class="mt-1 text-xs text-ink-500">{{ $payment->user->email ?? '—' }} · {{ ucfirst($payment->provider) }}</p>
                                <div class="mt-1 flex justify-between text-xs">
                                    <span class="text-ink-400">{{ $payment->created_at->format('M d, H:i') }}</span>
                                    <span class="font-bold text-ink-900">{{ xaf($payment->amount) }}</span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($payments->hasPages())
            <div>{{ $payments->links() }}</div>
        @endif
    </div>
</x-admin-layout>
