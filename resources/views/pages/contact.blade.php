<x-public-layout>
    <x-slot name="title">Contact Us</x-slot>

    <div class="bg-ink-50 border-b border-ink-200/70">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-center">
            <h1 class="text-3xl font-extrabold text-ink-900">Contact KamVerify</h1>
            <p class="mt-2 text-sm text-ink-500">We typically respond within 24 hours.</p>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="grid sm:grid-cols-3 gap-4 mb-10">
            <div class="kv-card p-5 text-center">
                <div class="w-11 h-11 mx-auto rounded-xl bg-brand-50 text-brand-600 grid place-items-center"><i class="fas fa-headset"></i></div>
                <h3 class="mt-3 font-semibold text-ink-900 text-sm">Support tickets</h3>
                <p class="mt-1 text-xs text-ink-500">Fastest for order, wallet, and account issues.</p>
                @auth
                    <a href="{{ route('support.index') }}" class="kv-btn-primary mt-4 w-full text-xs">Open support</a>
                @else
                    <a href="{{ route('login') }}" class="kv-btn-primary mt-4 w-full text-xs">Sign in</a>
                @endauth
            </div>
            <div class="kv-card p-5 text-center">
                <div class="w-11 h-11 mx-auto rounded-xl bg-emerald-50 text-emerald-600 grid place-items-center"><i class="fas fa-envelope"></i></div>
                <h3 class="mt-3 font-semibold text-ink-900 text-sm">Email</h3>
                <p class="mt-1 text-xs text-ink-500">General questions and partnership inquiries.</p>
                <a href="mailto:support@kamverify.com" class="mt-4 inline-block text-xs font-semibold text-brand-600 hover:text-brand-700">support@kamverify.com</a>
            </div>
            <div class="kv-card p-5 text-center">
                <div class="w-11 h-11 mx-auto rounded-xl bg-amber-50 text-amber-600 grid place-items-center"><i class="fas fa-circle-question"></i></div>
                <h3 class="mt-3 font-semibold text-ink-900 text-sm">FAQ</h3>
                <p class="mt-1 text-xs text-ink-500">Answers to the most common questions.</p>
                <a href="{{ route('home') }}#faq" class="mt-4 inline-block text-xs font-semibold text-brand-600 hover:text-brand-700">Browse FAQ</a>
            </div>
        </div>

        <div class="kv-card p-6 sm:p-8">
            <h2 class="font-bold text-ink-900">Send us a message</h2>
            <p class="text-sm text-ink-500 mt-1">For account-specific issues, please sign in and open a support ticket so we can verify your identity.</p>
            <form method="POST" action="{{ route('pages.contact') }}" class="mt-6 space-y-4">
                @csrf
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="kv-label">Name</label>
                        <input type="text" name="name" value="{{ old('name', auth()->user()?->name) }}" required class="kv-input" placeholder="Your name">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="kv-label">Email</label>
                        <input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required class="kv-input" placeholder="you@example.com">
                        @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="kv-label">Subject</label>
                    <input type="text" name="subject" value="{{ old('subject') }}" required class="kv-input" placeholder="How can we help?">
                    @error('subject')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="kv-label">Message</label>
                    <textarea name="message" rows="5" required class="kv-input" placeholder="Describe your question or issue...">{{ old('message') }}</textarea>
                    @error('message')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="kv-btn-primary w-full sm:w-auto">
                    <i class="fas fa-paper-plane"></i> Send message
                </button>
            </form>
        </div>
    </div>
</x-public-layout>
