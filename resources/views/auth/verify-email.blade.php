<x-guest-layout>
    <div class="mb-7 text-center">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-brand-50 text-brand-600 grid place-items-center text-xl">
            <i class="fas fa-envelope-open-text"></i>
        </div>
        <h1 class="mt-4 text-2xl font-extrabold text-ink-900 tracking-tight">Verify your email</h1>
        <p class="mt-2 text-sm text-ink-500 leading-relaxed">
            We sent a verification link to <strong class="text-ink-700">{{ auth()->user()->email }}</strong>.
            Click the link in that email to activate your account.
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-5 flex items-center gap-3 rounded-xl bg-emerald-50 border border-emerald-200/70 px-4 py-3">
            <i class="fas fa-check-circle text-emerald-600"></i>
            <p class="text-sm text-emerald-800">A new verification link has been sent.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" x-data="{ loading: false }" @submit="loading = true">
        @csrf
        <button type="submit" class="kv-btn-primary w-full h-11" :disabled="loading">
            <span x-show="!loading"><i class="fas fa-paper-plane mr-1.5"></i>Resend verification email</span>
            <span x-show="loading" x-cloak><i class="fas fa-circle-notch fa-spin mr-1.5"></i>Sending...</span>
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm font-medium text-ink-500 hover:text-ink-700">
            <i class="fas fa-arrow-right-from-bracket mr-1"></i> Sign out
        </button>
    </form>
</x-guest-layout>
