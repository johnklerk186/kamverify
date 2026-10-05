@php($promo = app(\App\Services\PromotionService::class)->summary())
@if($promo && $promo['status'] === 'active')
<div x-data="kvPromo({{ $promo['id'] }}, '{{ $promo['ends_at_iso'] }}')" x-show="!expired" x-cloak
     class="kv-card overflow-hidden relative">
    <div class="p-4 sm:p-5 bg-gradient-to-r from-brand-700 via-brand-800 to-ink-900 text-white relative">
        <div class="absolute -top-10 -right-10 w-36 h-36 rounded-full bg-cyan-400/15 blur-2xl pointer-events-none"></div>
        <div class="relative">
            <div class="flex items-center justify-between gap-3">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 border border-white/15 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-brand-100">
                    <i class="fas fa-bolt"></i> Weekly Promo
                </span>
                <span class="text-[11px] font-mono text-brand-100">
                    Ends in <span x-text="cd.d"></span>d <span x-text="cd.h"></span>h <span x-text="cd.m"></span>m <span x-text="cd.s"></span>s
                </span>
            </div>
            <p class="mt-2 text-sm font-bold">Special prices for 7 days</p>
            <p class="mt-1 text-xs text-ink-300">Exclusive prices on your favorite verification services — limited time only.</p>

            <a href="{{ route('orders.create') }}" class="mt-3.5 kv-btn w-full !py-2 bg-white text-ink-900 hover:bg-ink-100 text-xs font-bold justify-center">
                <i class="fas fa-cart-shopping"></i> Shop now
            </a>
        </div>
    </div>
</div>
@endif
