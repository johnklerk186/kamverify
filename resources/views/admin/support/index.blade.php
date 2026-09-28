<x-admin-layout>
    <x-slot name="title">Support</x-slot>
    <x-slot name="header">Support tickets</x-slot>

    <div class="space-y-5">
        @php
            $statuses = ['' => 'All', 'open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
            $current = request('status', '');
        @endphp
        <div class="flex gap-1.5 overflow-x-auto pb-1 -mx-1 px-1">
            @foreach($statuses as $key => $label)
                <a href="{{ route('admin.support.index', array_merge(request()->except('status', 'page'), $key ? ['status' => $key] : [])) }}"
                   class="flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-semibold transition
                          {{ $current === $key ? 'bg-ink-900 text-white' : 'bg-white border border-ink-200/70 text-ink-600 hover:border-ink-300' }}">
                    {{ $label }}
                    @if($key && isset($counts[$key]))
                        <span class="text-xs {{ $current === $key ? 'text-ink-300' : 'text-ink-400' }}">{{ $counts[$key] }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.support.index') }}" class="kv-card p-4 flex flex-col sm:flex-row gap-3">
            @if($current)<input type="hidden" name="status" value="{{ $current }}">@endif
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 text-xs"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Ticket ID, subject, or email…" class="kv-input !pl-9">
            </div>
            <select name="category" class="kv-input sm:w-44">
                <option value="">All categories</option>
                @foreach(['general','payment','order','technical','other'] as $c)
                    <option value="{{ $c }}" @selected(request('category') === $c)>{{ ucfirst($c) }}</option>
                @endforeach
            </select>
            <button type="submit" class="kv-btn-secondary shrink-0"><i class="fas fa-filter"></i> Filter</button>
        </form>

        <div class="kv-card overflow-hidden">
            @if($tickets->isEmpty())
                <x-empty-state icon="fa-headset" title="No tickets found" />
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach($tickets as $ticket)
                        <li>
                            <a href="{{ route('admin.support.show', $ticket) }}" class="flex items-center gap-4 px-5 sm:px-6 py-4 hover:bg-ink-50 transition">
                                <div class="w-10 h-10 rounded-xl grid place-items-center shrink-0
                                     {{ $ticket->status === 'open' ? 'bg-emerald-50 text-emerald-600' : ($ticket->status === 'in_progress' ? 'bg-sky-50 text-sky-600' : 'bg-ink-100 text-ink-500') }}">
                                    <i class="fas fa-ticket"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-ink-900 text-sm truncate">{{ $ticket->subject }}</p>
                                    <p class="mt-0.5 text-xs text-ink-400 font-mono truncate">
                                        {{ $ticket->ticket_id }} · {{ $ticket->user->email ?? '—' }} · {{ ucfirst($ticket->category) }} · {{ $ticket->messages_count }} msg · {{ $ticket->updated_at->diffForHumans() }}
                                    </p>
                                </div>
                                @if($ticket->priority && $ticket->priority !== 'medium')
                                    <x-status-badge :status="$ticket->priority" />
                                @endif
                                <x-status-badge :status="$ticket->status" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($tickets->hasPages())
            <div>{{ $tickets->links() }}</div>
        @endif
    </div>
</x-admin-layout>
