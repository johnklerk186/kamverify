<x-app-layout>
    <x-slot name="title">New Ticket</x-slot>

    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('support.index') }}" class="w-9 h-9 rounded-xl border border-ink-200 bg-white grid place-items-center text-ink-500 hover:text-ink-800 hover:border-ink-300 transition">
                <i class="fas fa-arrow-left text-sm"></i>
            </a>
            <div>
                <h1 class="text-2xl font-extrabold text-ink-900 tracking-tight">Create a ticket</h1>
                <p class="mt-0.5 text-sm text-ink-500">We typically respond within 24 hours.</p>
            </div>
        </div>

        <div class="kv-card p-6 sm:p-8" x-data="{ loading: false }">
            <form method="POST" action="{{ route('support.store') }}" @submit="loading = true">
                @csrf

                <div>
                    <span class="kv-label">What is this about?</span>
                    <div class="grid grid-cols-2 gap-2.5" x-data="{ cat: '{{ old('category', request('category', 'general')) }}' }">
                        @foreach([
                            'order'     => ['fa-mobile-screen', 'Order issue', 'Numbers, SMS, activation'],
                            'payment'   => ['fa-wallet', 'Billing', 'Deposits, refunds, balance'],
                            'technical' => ['fa-screwdriver-wrench', 'Technical', 'Site or app problems'],
                            'other'     => ['fa-circle-question', 'Other', 'Everything else'],
                        ] as $value => [$icon, $label, $desc])
                            <label class="cursor-pointer">
                                <input type="radio" name="category" value="{{ $value }}" class="peer sr-only" x-model="cat">
                                <div class="rounded-xl border px-4 py-3.5 transition peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:ring-1 peer-checked:ring-brand-500 border-ink-200/70 hover:border-brand-300">
                                    <div class="flex items-center gap-2.5">
                                        <i class="fas {{ $icon }} text-brand-600"></i>
                                        <span class="text-sm font-semibold text-ink-900">{{ $label }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-ink-500">{{ $desc }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('category')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mt-5">
                    <label for="subject" class="kv-label">Subject</label>
                    <input id="subject" type="text" name="subject" value="{{ old('subject') }}" required maxlength="255"
                           class="kv-input @error('subject') border-red-400 focus:ring-red-500 @enderror"
                           placeholder="Brief summary of your issue">
                    @error('subject')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mt-5">
                    <label for="message" class="kv-label">Message</label>
                    <textarea id="message" name="message" rows="6" required maxlength="5000"
                              class="kv-input @error('message') border-red-400 focus:ring-red-500 @enderror"
                              placeholder="Describe the problem in detail. Include order IDs if relevant.">{{ old('message') }}</textarea>
                    @error('message')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="kv-btn-primary w-full h-11 mt-6" :disabled="loading">
                    <span x-show="!loading"><i class="fas fa-paper-plane"></i> Submit ticket</span>
                    <span x-show="loading" x-cloak><i class="fas fa-circle-notch fa-spin"></i> Submitting…</span>
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
