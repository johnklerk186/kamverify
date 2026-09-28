<x-app-layout>
    <x-slot name="title">Support</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-ink-900 tracking-tight">Support center</h1>
                <p class="mt-1 text-sm text-ink-500">Get help with orders, payments, and your account.</p>
            </div>
            <a href="{{ route('support.create') }}" class="kv-btn-primary shrink-0"><i class="fas fa-plus"></i> New ticket</a>
        </div>

        {{-- Quick help --}}
        <div class="grid sm:grid-cols-3 gap-4">
            <a href="{{ route('support.create') }}?category=billing" class="kv-card p-5 hover:border-brand-300 transition group">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 grid place-items-center"><i class="fas fa-wallet"></i></div>
                <h3 class="mt-3 font-semibold text-ink-900 text-sm group-hover:text-brand-700">Billing &amp; wallet</h3>
                <p class="mt-1 text-xs text-ink-500">Deposits, refunds, missing credits.</p>
            </a>
            <a href="{{ route('support.create') }}?category=technical" class="kv-card p-5 hover:border-brand-300 transition group">
                <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 grid place-items-center"><i class="fas fa-mobile-screen"></i></div>
                <h3 class="mt-3 font-semibold text-ink-900 text-sm group-hover:text-brand-700">Order issues</h3>
                <p class="mt-1 text-xs text-ink-500">Numbers, missing SMS, order status.</p>
            </a>
            <a href="{{ route('support.create') }}" class="kv-card p-5 hover:border-brand-300 transition group">
                <div class="w-10 h-10 rounded-xl bg-violet-50 text-violet-600 grid place-items-center"><i class="fas fa-circle-question"></i></div>
                <h3 class="mt-3 font-semibold text-ink-900 text-sm group-hover:text-brand-700">Something else</h3>
                <p class="mt-1 text-xs text-ink-500">Account, referrals, general questions.</p>
            </a>
        </div>

        {{-- Tickets --}}
        <div class="kv-card overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-ink-100">
                <h2 class="font-bold text-ink-900 text-sm">Your tickets</h2>
            </div>
            @if($tickets->isEmpty())
                <x-empty-state icon="fa-headset" title="No support tickets" message="Open a ticket and our team will get back to you.">
                    <a href="{{ route('support.create') }}" class="kv-btn-primary"><i class="fas fa-plus"></i> Create ticket</a>
                </x-empty-state>
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach($tickets as $ticket)
                        <li>
                            <a href="{{ route('support.show', $ticket) }}" class="flex items-center gap-4 px-5 sm:px-6 py-4 hover:bg-ink-50 transition">
                                <div class="w-10 h-10 rounded-xl grid place-items-center shrink-0
                                     {{ $ticket->status === 'open' ? 'bg-emerald-50 text-emerald-600' : ($ticket->status === 'in_progress' ? 'bg-sky-50 text-sky-600' : 'bg-ink-100 text-ink-500') }}">
                                    <i class="fas fa-ticket"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="font-semibold text-ink-900 text-sm truncate">{{ $ticket->subject }}</p>
                                    </div>
                                    <p class="mt-0.5 text-xs text-ink-400 font-mono">{{ $ticket->ticket_id }} · {{ ucfirst($ticket->category) }} · {{ $ticket->created_at->diffForHumans() }}</p>
                                </div>
                                <x-status-badge :status="$ticket->status" />
                                <i class="fas fa-chevron-right text-xs text-ink-300"></i>
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
</x-app-layout>
