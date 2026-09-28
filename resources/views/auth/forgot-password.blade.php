<x-guest-layout>
    <div class="mb-7">
        <h1 class="text-2xl font-extrabold text-ink-900 tracking-tight">Reset your password</h1>
        <p class="mt-1 text-sm text-ink-500">Enter your account email and we'll send you a reset link.</p>
    </div>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" x-data="{ loading: false }" @submit="loading = true">
        @csrf

        <div>
            <label for="email" class="kv-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="kv-input @error('email') border-red-400 focus:ring-red-500 @enderror" placeholder="you@example.com">
            @error('email')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="kv-btn-primary w-full mt-6 h-11" :disabled="loading">
            <span x-show="!loading"><i class="fas fa-envelope mr-1.5"></i>Send reset link</span>
            <span x-show="loading" x-cloak><i class="fas fa-circle-notch fa-spin mr-1.5"></i>Sending...</span>
        </button>
    </form>

    <p class="mt-7 text-center text-sm text-ink-500">
        Remembered it?
        <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">Back to sign in</a>
    </p>
</x-guest-layout>
