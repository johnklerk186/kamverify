<x-app-layout>
    <x-slot name="title">Notifications</x-slot>

    <div class="max-w-3xl mx-auto space-y-5">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-ink-900 tracking-tight">Notifications</h1>
                <p class="mt-1 text-sm text-ink-500">Order updates, deposits, and account activity.</p>
            </div>
            @if(auth()->user()->unreadNotifications->isNotEmpty())
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="kv-btn-secondary !py-2 text-xs">
                        <i class="fas fa-check-double"></i> Mark all read
                    </button>
                </form>
            @endif
        </div>

        <div class="kv-card overflow-hidden">
            @if($notifications->isEmpty())
                <x-empty-state icon="fa-bell-slash" title="No notifications" message="Order updates and account activity will appear here." />
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach($notifications as $notification)
                        @php $data = $notification->data; @endphp
                        <li class="flex items-start gap-4 px-5 sm:px-6 py-4 {{ is_null($notification->read_at) ? 'bg-brand-50/40' : '' }}">
                            <div class="w-9 h-9 rounded-xl grid place-items-center shrink-0
                                 {{ is_null($notification->read_at) ? 'bg-brand-100 text-brand-600' : 'bg-ink-100 text-ink-400' }}">
                                <i class="fas {{ $data['icon'] ?? (str_contains(class_basename($notification->type), 'Sms') ? 'fa-message' : (str_contains(class_basename($notification->type), 'Deposit') ? 'fa-wallet' : 'fa-bell')) }} text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-ink-900">{{ $data['title'] ?? class_basename($notification->type) }}</p>
                                <p class="mt-0.5 text-sm text-ink-600">{{ $data['message'] ?? ($data['body'] ?? '') }}</p>
                                <div class="mt-1 flex items-center gap-3">
                                    <p class="text-xs text-ink-400">{{ $notification->created_at->diffForHumans() }}</p>
                                    @if(!empty($data['action_url']))
                                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-brand-600 hover:text-brand-700">
                                                {{ $data['action_text'] ?? 'View' }} <i class="fas fa-arrow-right text-[10px]"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            @if(is_null($notification->read_at))
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-brand-600 hover:text-brand-700 whitespace-nowrap">
                                        Mark read
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($notifications->hasPages())
            <div>{{ $notifications->links() }}</div>
        @endif
    </div>
</x-app-layout>
