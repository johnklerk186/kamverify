<x-guest-layout>
    <div class="text-center py-4">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-50 text-amber-500 grid place-items-center">
            <i class="fas fa-clock-rotate-left text-xl"></i>
        </div>
        <h1 class="mt-5 text-2xl font-extrabold text-ink-900 tracking-tight">This page expired</h1>
        <p class="mt-2 text-sm text-ink-500 leading-relaxed">
            The page was open too long and its security token expired. Nothing was submitted or charged — just reload and try again.
        </p>
        <div class="mt-6 flex flex-col gap-2.5">
            <a href="{{ url()->current() }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white hover:bg-brand-700 transition">
                <i class="fas fa-rotate-right"></i> Reload this page
            </a>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-ink-200 px-5 py-3 text-sm font-semibold text-ink-600 hover:bg-ink-50 transition">
                Back to dashboard
            </a>
        </div>
    </div>
</x-guest-layout>
