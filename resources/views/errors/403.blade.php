<x-guest-layout>
    <div class="text-center py-4">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-ink-100 text-ink-400 grid place-items-center">
            <i class="fas fa-link-slash text-xl"></i>
        </div>
        <h1 class="mt-5 text-2xl font-extrabold text-ink-900 tracking-tight">This link is no longer valid</h1>
        <p class="mt-2 text-sm text-ink-500 leading-relaxed">
            The link may have expired or already been used. Sign in and request a fresh link, or return to the dashboard.
        </p>
        <div class="mt-6 flex flex-col gap-2.5">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white hover:bg-brand-700 transition">
                    Back to dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white hover:bg-brand-700 transition">
                    <i class="fas fa-arrow-right-to-bracket"></i> Sign in
                </a>
            @endauth
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-ink-200 px-5 py-3 text-sm font-semibold text-ink-600 hover:bg-ink-50 transition">
                KamVerify home
            </a>
        </div>
    </div>
</x-guest-layout>
