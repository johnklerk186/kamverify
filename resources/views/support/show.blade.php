<x-app-layout>
    <x-slot name="title">{{ $ticket->subject }}</x-slot>

    <div class="max-w-3xl mx-auto space-y-5">
        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('support.index') }}" class="w-9 h-9 rounded-xl border border-ink-200 bg-white grid place-items-center text-ink-500 hover:text-ink-800 hover:border-ink-300 transition">
                    <i class="fas fa-arrow-left text-sm"></i>
                </a>
                <div>
                    <h1 class="text-xl font-extrabold text-ink-900 tracking-tight">{{ $ticket->subject }}</h1>
                    <p class="text-xs text-ink-500 font-mono mt-0.5">{{ $ticket->ticket_id }} · {{ ucfirst($ticket->category) }} · Opened {{ $ticket->created_at->format('M d, Y') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <x-status-badge :status="$ticket->status" />
                @if($ticket->status !== 'closed')
                    <form method="POST" action="{{ route('support.close', $ticket) }}" x-data="{ confirmClose: false }">
                        @csrf
                        <button type="button" x-show="!confirmClose" @click="confirmClose = true" class="kv-btn-ghost !py-2 text-xs">
                            <i class="fas fa-xmark"></i> Close ticket
                        </button>
                        <button type="submit" x-show="confirmClose" x-cloak class="kv-btn-danger !py-2 text-xs">
                            Confirm close?
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Conversation --}}
        <div class="kv-card">
            <div class="px-5 sm:px-6 py-4 border-b border-ink-100">
                <h2 class="font-bold text-ink-900 text-sm">Conversation</h2>
            </div>
            <div class="divide-y divide-ink-100">
                @foreach($messages as $message)
                    <div class="px-5 sm:px-6 py-5 {{ $message->is_admin ? 'bg-brand-50/40' : '' }}">
                        <div class="flex items-start gap-3.5">
                            <div class="w-9 h-9 rounded-full grid place-items-center text-xs font-bold shrink-0
                                 {{ $message->is_admin ? 'bg-brand-600 text-white' : 'bg-ink-200 text-ink-700' }}">
                                {{ $message->is_admin ? 'KV' : strtoupper(substr($message->user->name ?? 'Y', 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-sm font-semibold text-ink-900">
                                        {{ $message->is_admin ? 'KamVerify Support' : ($message->user->name ?? 'You') }}
                                    </p>
                                    @if($message->is_admin)
                                        <span class="kv-badge bg-brand-100 text-brand-700">Staff</span>
                                    @endif
                                    <span class="text-xs text-ink-400">{{ $message->created_at->format('M d, Y H:i') }}</span>
                                </div>
                                <p class="mt-1.5 text-sm text-ink-700 leading-relaxed whitespace-pre-wrap">{{ $message->message }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Reply --}}
            @if($ticket->status !== 'closed')
                <div class="px-5 sm:px-6 py-5 border-t border-ink-100 bg-ink-50/50" x-data="{ loading: false }">
                    <form method="POST" action="{{ route('support.reply', $ticket) }}" @submit="loading = true">
                        @csrf
                        <label for="message" class="kv-label">Your reply</label>
                        <textarea id="message" name="message" rows="4" required maxlength="5000"
                                  class="kv-input @error('message') border-red-400 focus:ring-red-500 @enderror"
                                  placeholder="Type your message…">{{ old('message') }}</textarea>
                        @error('message')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                        <div class="mt-3 flex justify-end">
                            <button type="submit" class="kv-btn-primary" :disabled="loading">
                                <span x-show="!loading"><i class="fas fa-paper-plane"></i> Send reply</span>
                                <span x-show="loading" x-cloak><i class="fas fa-circle-notch fa-spin"></i> Sending…</span>
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="px-5 sm:px-6 py-5 border-t border-ink-100 bg-ink-50/50 text-center">
                    <p class="text-sm text-ink-500">This ticket is closed.</p>
                    <form method="POST" action="{{ route('support.reply', $ticket) }}" class="mt-3" x-data="{ loading: false }" @submit="loading = true">
                        @csrf
                        <input type="hidden" name="message" value="Reopening this ticket — I still need help.">
                        <button type="submit" class="kv-btn-secondary" :disabled="loading">
                            <i class="fas fa-rotate-left"></i> Reopen ticket
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
