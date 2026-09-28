<x-admin-layout>
    <x-slot name="title">Notifications</x-slot>
    <x-slot name="header">Notification log</x-slot>

    <div class="space-y-5">
        <div class="flex justify-end">
            <a href="{{ route('admin.notifications.create') }}" class="kv-btn-primary"><i class="fas fa-paper-plane"></i> Send notification</a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <x-stat-card label="Total sent" :value="number_format($stats['total'])" icon="fa-bell" accent="brand" />
            <x-stat-card label="Unread" :value="number_format($stats['unread'])" icon="fa-envelope" accent="amber" />
            <x-stat-card label="Today" :value="number_format($stats['today'])" icon="fa-calendar-day" accent="blue" />
        </div>

        <form method="GET" action="{{ route('admin.notifications.index') }}" class="kv-card p-4 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 text-xs"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Type or customer email…" class="kv-input !pl-9">
            </div>
            <select name="type" class="kv-input sm:w-52">
                <option value="">All types</option>
                @foreach(['OrderCreated','SmsReceived','DepositSuccessful','KamVerifyNotification'] as $t)
                    <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
                @endforeach
            </select>
            <button type="submit" class="kv-btn-secondary shrink-0"><i class="fas fa-filter"></i> Filter</button>
        </form>

        <div class="kv-card overflow-hidden">
            @if($notifications->isEmpty())
                <x-empty-state icon="fa-bell-slash" title="No notifications" />
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach($notifications as $n)
                        <li class="flex items-start gap-4 px-5 sm:px-6 py-4">
                            <div class="w-9 h-9 rounded-xl grid place-items-center shrink-0 {{ is_null($n->read_at) ? 'bg-brand-100 text-brand-600' : 'bg-ink-100 text-ink-400' }}">
                                <i class="fas {{ str_contains($n->short_type, 'Sms') ? 'fa-message' : (str_contains($n->short_type, 'Deposit') ? 'fa-wallet' : 'fa-bell') }} text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-sm font-semibold text-ink-900">{{ $n->short_type }}</p>
                                    @if(is_null($n->read_at))
                                        <span class="kv-badge bg-brand-100 text-brand-700">Unread</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-sm text-ink-600">{{ $n->data['message'] ?? ($n->data['title'] ?? json_encode($n->data)) }}</p>
                                <p class="mt-1 text-xs text-ink-400">{{ $n->user_email ?? 'unknown user' }} · {{ \Carbon\Carbon::parse($n->created_at)->diffForHumans() }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($notifications->hasPages())
            <div>{{ $notifications->links() }}</div>
        @endif
    </div>
</x-admin-layout>
