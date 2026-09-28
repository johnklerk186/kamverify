<x-admin-layout>
    <x-slot name="title">Users</x-slot>
    <x-slot name="header">Customers</x-slot>

    <div class="space-y-5">
        <form method="GET" action="{{ route('admin.users.index') }}" class="kv-card p-4 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 text-xs"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email…" class="kv-input !pl-9">
            </div>
            <button type="submit" class="kv-btn-secondary shrink-0"><i class="fas fa-filter"></i> Search</button>
        </form>

        <div class="kv-card overflow-hidden">
            @if($users->isEmpty())
                <x-empty-state icon="fa-users" title="No customers found" message="Registered customers will appear here." />
            @else
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-ink-50/70 border-b border-ink-100">
                            <tr>
                                <th class="kv-th">Customer</th>
                                <th class="kv-th">Balance</th>
                                <th class="kv-th">Orders</th>
                                <th class="kv-th">Status</th>
                                <th class="kv-th">Joined</th>
                                <th class="kv-th"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach($users as $u)
                                <tr class="hover:bg-ink-50/60 transition">
                                    <td class="kv-td">
                                        <div class="flex items-center gap-3">
                                            <span class="w-9 h-9 rounded-full bg-brand-100 text-brand-700 grid place-items-center text-xs font-bold shrink-0">
                                                {{ strtoupper(substr($u->name, 0, 1)) }}
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-ink-900 truncate">{{ $u->name }}</p>
                                                <p class="text-xs text-ink-400 truncate">{{ $u->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="kv-td font-semibold text-ink-900">{{ xaf($u->wallet->balance ?? 0) }}</td>
                                    <td class="kv-td text-ink-600">{{ $u->orders_count ?? $u->orders()->count() }}</td>
                                    <td class="kv-td"><x-status-badge :status="$u->is_active ? 'active' : 'suspended'" /></td>
                                    <td class="kv-td text-xs text-ink-500">{{ $u->created_at->format('M d, Y') }}</td>
                                    <td class="kv-td text-right">
                                        <a href="{{ route('admin.users.show', $u) }}" class="text-brand-600 hover:text-brand-700 font-semibold text-sm">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <ul class="md:hidden divide-y divide-ink-100">
                    @foreach($users as $u)
                        <li>
                            <a href="{{ route('admin.users.show', $u) }}" class="flex items-center gap-3 px-5 py-4 active:bg-ink-50">
                                <span class="w-9 h-9 rounded-full bg-brand-100 text-brand-700 grid place-items-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($u->name, 0, 1)) }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-ink-900 truncate">{{ $u->name }}</p>
                                    <p class="text-xs text-ink-400 truncate">{{ $u->email }}</p>
                                </div>
                                <x-status-badge :status="$u->is_active ? 'active' : 'suspended'" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($users->hasPages())
            <div>{{ $users->links() }}</div>
        @endif
    </div>
</x-admin-layout>
