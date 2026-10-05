@php($promo = app(\App\Services\PromotionService::class)->summary())
@if($promo && $promo['status'] === 'active')
<section class="bg-white border-b border-ink-200/70" x-data="kvPromo({{ $promo['id'] }}, '{{ $promo['ends_at_iso'] }}')" x-show="!dismissed && !expired" x-cloak>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
        <div class="relative overflow-hidden rounded-2xl bg-ink-900 text-white shadow-pop">
            <div class="absolute inset-0 bg-gradient-to-r from-brand-800/80 via-ink-900 to-cyan-900/40 pointer-events-none"></div>
            <div class="absolute -top-20 -right-16 w-56 h-56 rounded-full bg-brand-500/20 blur-3xl pointer-events-none"></div>

            <button type="button" @click="dismiss()" aria-label="Dismiss promotion"
                    class="absolute top-3 right-3 z-10 w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-ink-300 hover:text-white transition grid place-items-center">
                <i class="fas fa-times text-xs"></i>
            </button>

            <div class="relative px-5 sm:px-8 py-5 sm:py-6 flex flex-col md:flex-row md:items-center gap-5 md:gap-8">
                <div class="flex-1 min-w-0">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/20 border border-brand-400/30 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-brand-200">
                        <i class="fas fa-bolt"></i> Weekly Promo
                    </span>
                    <h3 class="mt-2.5 text-xl sm:text-2xl font-extrabold tracking-tight">Get verified for less</h3>
                    <p class="mt-1 text-sm text-ink-300">Limited-time prices. Back to normal after the promotion.</p>

                    <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                        <li class="flex items-center gap-2">
                            <i class="fab fa-facebook text-brand-300"></i>
                            <span class="text-ink-300">Facebook</span>
                            <strong class="text-white">{{ xaf($promo['facebook_price'] ?? 0) }}</strong>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fab fa-whatsapp text-emerald-300"></i>
                            <span class="text-ink-300">WhatsApp</span>
                            <strong class="text-white">From {{ xaf($promo['whatsapp_us_price'] ?? 0) }}</strong>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fab fa-telegram text-sky-300"></i>
                            <span class="text-ink-300">Telegram</span>
                            <strong class="text-white">{{ xaf($promo['telegram_price'] ?? 0) }}</strong>
                        </li>
                    </ul>
                </div>

                <div class="shrink-0 flex flex-col items-start md:items-end gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-400 mb-1.5">Offer ends in</p>
                        <div class="flex items-center gap-1.5 font-mono">
                            <span class="rounded-lg bg-white/10 border border-white/10 px-2 py-1.5 text-sm font-bold min-w-[2.5rem] text-center" x-text="cd.d"></span><span class="text-ink-500 text-xs">d</span>
                            <span class="rounded-lg bg-white/10 border border-white/10 px-2 py-1.5 text-sm font-bold min-w-[2.5rem] text-center" x-text="cd.h"></span><span class="text-ink-500 text-xs">h</span>
                            <span class="rounded-lg bg-white/10 border border-white/10 px-2 py-1.5 text-sm font-bold min-w-[2.5rem] text-center" x-text="cd.m"></span><span class="text-ink-500 text-xs">m</span>
                            <span class="rounded-lg bg-white/10 border border-white/10 px-2 py-1.5 text-sm font-bold min-w-[2.5rem] text-center" x-text="cd.s"></span><span class="text-ink-500 text-xs">s</span>
                        </div>
                    </div>
                    <a href="{{ auth()->check() ? route('orders.create') : route('register') }}"
                       class="kv-btn-primary !px-6 !py-2.5 w-full md:w-auto text-center">
                        <i class="fas fa-bolt"></i> Get a number
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
@endif
