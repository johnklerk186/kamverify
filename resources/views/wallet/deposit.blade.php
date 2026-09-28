<x-app-layout>
    <x-slot name="title">Deposit Funds</x-slot>

    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('wallet.index') }}" class="w-9 h-9 rounded-xl border border-ink-200 bg-white grid place-items-center text-ink-500 hover:text-ink-800 hover:border-ink-300 transition">
                <i class="fas fa-arrow-left text-sm"></i>
            </a>
            <div>
                <h1 class="text-2xl font-extrabold text-ink-900 tracking-tight">Deposit Wallet</h1>
                <p class="mt-0.5 text-sm text-ink-500">Current balance: <strong class="text-ink-900">{{ xaf($wallet->balance) }}</strong></p>
            </div>
        </div>

        @if($sandbox)
            <div class="rounded-xl bg-sky-50 border border-sky-200/70 px-4 py-3 flex items-start gap-3">
                <i class="fas fa-flask text-sky-600 mt-0.5"></i>
                <div class="text-sm text-sky-800">
                    <strong>Sandbox mode.</strong> This deposit is simulated — no real payment is made and funds credit instantly.
                    Live MTN Mobile Money activates automatically once Fapshi credentials are configured.
                </div>
            </div>
        @endif

        <div class="kv-card p-6 sm:p-8" x-data="{ amount: '{{ old('amount') }}', phone: '{{ old('phone') }}', loading: false }">
            <form method="POST" action="{{ route('wallet.process-deposit') }}" @submit="loading = true">
                @csrf

                <label for="amount" class="kv-label">Amount (XAF)</label>
                <div class="relative">
                    <input id="amount" type="number" name="amount" x-model="amount" step="1" inputmode="numeric"
                           min="{{ $minDeposit }}" max="{{ $maxDeposit }}" required
                           class="kv-input !text-lg !font-bold !py-3.5 @error('amount') border-red-400 focus:ring-red-500 @enderror"
                           placeholder="5000">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-ink-400 font-semibold text-sm">XAF</span>
                </div>
                @error('amount')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                <p class="mt-1.5 text-xs text-ink-400">Min {{ xaf($minDeposit) }} · Max {{ xaf($maxDeposit) }}</p>

                <div class="mt-3 grid grid-cols-4 gap-2">
                    @foreach([500, 1000, 5000, 10000] as $preset)
                        <button type="button" @click="amount = '{{ $preset }}'"
                                class="rounded-xl border px-2 py-2.5 text-xs sm:text-sm font-bold transition"
                                :class="amount == '{{ $preset }}' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-ink-200 text-ink-600 hover:border-brand-300 hover:bg-ink-50'">
                            {{ number_format($preset) }}
                        </button>
                    @endforeach
                </div>

                <div class="mt-7">
                    <span class="kv-label">Payment Method</span>
                    @if($sandbox)
                        <input type="hidden" name="payment_method" value="mock">
                    @else
                        <input type="hidden" name="payment_method" value="mtn_momo">
                    @endif

                    {{-- MTN Mobile Money — the only active method --}}
                    <div class="flex items-center gap-4 rounded-xl border-2 border-brand-500 bg-brand-50/60 px-4 py-4">
                        <div class="w-10 h-10 rounded-xl bg-yellow-400 grid place-items-center shrink-0">
                            <span class="text-[10px] font-black text-ink-900 tracking-tight">MTN</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-ink-900 text-sm">MTN Mobile Money</p>
                            <p class="text-xs text-ink-500">You'll be redirected to a secure payment page</p>
                        </div>
                        <i class="fas fa-circle-check text-brand-600 text-lg shrink-0"></i>
                    </div>

                    {{-- Other methods — display only, not selectable --}}
                    <div class="mt-2.5 grid grid-cols-3 gap-2">
                        <div class="flex items-center gap-2 rounded-xl border border-ink-200/70 bg-ink-50/60 px-3 py-2.5 opacity-60">
                            <i class="fas fa-mobile-screen text-orange-500"></i>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-ink-700 truncate">Orange Money</p>
                                <p class="text-[10px] text-ink-400">Coming soon</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 rounded-xl border border-ink-200/70 bg-ink-50/60 px-3 py-2.5 opacity-60">
                            <i class="fab fa-bitcoin text-amber-500"></i>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-ink-700 truncate">Bitcoin</p>
                                <p class="text-[10px] text-ink-400">Coming soon</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 rounded-xl border border-ink-200/70 bg-ink-50/60 px-3 py-2.5 opacity-60">
                            <i class="fas fa-credit-card text-ink-500"></i>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-ink-700 truncate">Bank Card</p>
                                <p class="text-[10px] text-ink-400">Coming soon</p>
                            </div>
                        </div>
                    </div>
                    @error('payment_method')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                @if(!$sandbox)
                    <p class="mt-5 text-xs text-ink-400 flex items-start gap-2">
                        <i class="fas fa-lock mt-0.5"></i>
                        You'll be redirected to Fapshi's secure checkout to enter your mobile money number and approve the payment. Your wallet is credited only after the payment is verified.
                    </p>
                @endif

                <button type="submit" class="kv-btn-primary w-full h-12 mt-7 text-base" :disabled="loading || !amount || amount < {{ $minDeposit }}">
                    <span x-show="!loading"><i class="fas fa-arrow-right-to-bracket"></i> {{ $sandbox ? 'Simulate deposit' : 'Continue to Payment' }}</span>
                    <span x-show="loading" x-cloak><i class="fas fa-circle-notch fa-spin"></i> Processing…</span>
                </button>
            </form>
        </div>

        <div class="grid sm:grid-cols-3 gap-3 text-center">
            <div class="kv-card p-4">
                <i class="fas fa-shield-halved text-brand-600"></i>
                <p class="mt-1.5 text-xs font-semibold text-ink-700">Verified credits only</p>
                <p class="text-[11px] text-ink-400">Backend-confirmed payments</p>
            </div>
            <div class="kv-card p-4">
                <i class="fas fa-bolt text-brand-600"></i>
                <p class="mt-1.5 text-xs font-semibold text-ink-700">Quick crediting</p>
                <p class="text-[11px] text-ink-400">Funds usable on confirmation</p>
            </div>
            <div class="kv-card p-4">
                <i class="fas fa-rotate-left text-brand-600"></i>
                <p class="mt-1.5 text-xs font-semibold text-ink-700">Auto refunds</p>
                <p class="text-[11px] text-ink-400">Failed orders refund fully</p>
            </div>
        </div>
    </div>
</x-app-layout>
