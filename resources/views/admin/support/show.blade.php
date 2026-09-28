<x-admin-layout>
    <x-slot name="title">{{ $ticket->subject }}</x-slot>
    <x-slot name="header">Ticket {{ $ticket->ticket_id }}</x-slot>

    <div class="max-w-4xl space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.support.index') }}" class="w-9 h-9 rounded-xl border border-ink-200 bg-white grid place-items-center text-ink-500 hover:text-ink-800 transition">
                    <i class="fas fa-arrow-left text-sm"></i>
                </a>
                <div>
                    <h1 class="text-lg font-extrabold text-ink-900">{{ $ticket->subject }}</h1>
                    <p class="text-xs text-ink-500 font-mono mt-0.5">
                        {{ $ticket->ticket_id }} · {{ $ticket->user->email ?? '—' }} · {{ ucfirst($ticket->category) }} · {{ $ticket->created_at->format('M d, Y H:i') }}
                    </p>
                </div>
            </div>
            <x-status-badge :status="$ticket->status" />
        </div>

        <div class="grid lg:grid-cols-3 gap-5 items-start">
            {{-- Conversation --}}
            <div class="lg:col-span-2 kv-card overflow-hidden">
                <div class="px-5 py-4 border-b border-ink-100"><h2 class="font-bold text-ink-900 text-sm">Conversation</h2></div>
                <div class="divide-y divide-ink-100">
                    @foreach($ticket->messages as $message)
                        <div class="px-5 py-5 {{ $message->is_admin ? 'bg-brand-50/40' : '' }}">
                            <div class="flex items-start gap-3.5">
                                <div class="w-9 h-9 rounded-full grid place-items-center text-xs font-bold shrink-0
                                     {{ $message->is_admin ? 'bg-brand-600 text-white' : 'bg-ink-200 text-ink-700' }}">
                                    {{ $message->is_admin ? 'KV' : strtoupper(substr($message->user->name ?? 'U', 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-sm font-semibold text-ink-900">{{ $message->is_admin ? 'Support' : ($message->user->name ?? 'User') }}</p>
                                        @if($message->is_admin)
                                            <span class="kv-badge bg-brand-100 text-brand-700">Staff</span>
                                        @endif
                                        <span class="text-xs text-ink-400">{{ $message->created_at->format('M d, H:i') }}</span>
                                    </div>
                                    <p class="mt-1.5 text-sm text-ink-700 whitespace-pre-wrap break-words">{{ $message->message }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="px-5 py-5 border-t border-ink-100 bg-ink-50/50" x-data="{ loading: false }">
                    <form method="POST" action="{{ route('admin.support.reply', $ticket) }}" @submit="loading = true">
                        @csrf
                        <textarea name="message" rows="4" required maxlength="5000" class="kv-input" placeholder="Reply to customer…"></textarea>
                        @error('message')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                        <div class="mt-3 flex justify-end">
                            <button type="submit" class="kv-btn-primary" :disabled="loading">
                                <span x-show="!loading"><i class="fas fa-paper-plane"></i> Send reply</span>
                                <span x-show="loading" x-cloak><i class="fas fa-circle-notch fa-spin"></i> Sending…</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-5">
                <div class="kv-card p-5">
                    <h3 class="font-bold text-ink-900 text-sm">Customer</h3>
                    <a href="{{ route('admin.users.show', $ticket->user) }}" class="mt-3 flex items-center gap-3 rounded-xl border border-ink-200/70 p-3 hover:border-brand-300 transition">
                        <span class="w-9 h-9 rounded-full bg-brand-100 text-brand-700 grid place-items-center text-xs font-bold">
                            {{ strtoupper(substr($ticket->user->name ?? 'U', 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink-900 truncate">{{ $ticket->user->name ?? '—' }}</p>
                            <p class="text-xs text-ink-400 truncate">{{ $ticket->user->email ?? '—' }}</p>
                        </div>
                    </a>
                </div>

                <div class="kv-card p-5">
                    <h3 class="font-bold text-ink-900 text-sm">Manage</h3>
                    <form method="POST" action="{{ route('admin.support.update', $ticket) }}" class="mt-4 space-y-4">
                        @csrf @method('PUT')
                        <div>
                            <label class="kv-label !mb-1 text-xs">Status</label>
                            <select name="status" class="kv-input !py-2">
                                @foreach(['open','in_progress','resolved','closed'] as $s)
                                    <option value="{{ $s }}" @selected($ticket->status === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="kv-label !mb-1 text-xs">Priority</label>
                            <select name="priority" class="kv-input !py-2">
                                @foreach(['low','medium','high','urgent'] as $p)
                                    <option value="{{ $p }}" @selected($ticket->priority === $p)>{{ ucfirst($p) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="kv-btn-primary w-full !py-2 text-xs"><i class="fas fa-check"></i> Update ticket</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
