<x-admin-layout>
    <x-slot name="title">Promotions</x-slot>
    <x-slot name="header">Promotions</x-slot>

    <div class="space-y-6">
        <div class="rounded-xl bg-ink-50 border border-ink-200/70 px-4 py-3 text-sm text-ink-600">
            <i class="fas fa-bullhorn text-brand-600 mr-1.5"></i>
            A promotion is a temporary pricing layer — normal prices are never modified. When a promotion's window ends, normal pricing returns automatically. Promo prices are floored at provider cost, so a promoted combination can never sell at a loss.
        </div>

        <div class="flex items-center justify-between">
            <h2 class="font-bold text-ink-900">Campaigns</h2>
            <a href="{{ route('admin.promotions.create') }}" class="kv-btn-primary !py-2 text-xs"><i class="fas fa-plus"></i> New promotion</a>
        </div>

        @if($promotions->isEmpty())
            <div class="kv-card p-10 text-center text-sm text-ink-400">
                No promotions yet. Create one to launch the weekly promo pricing.
            </div>
        @else
            <div class="space-y-4">
                @foreach($promotions as $promo)
                    @php $status = $promo->status(); @endphp
                    <div class="kv-card p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <h3 class="font-bold text-ink-900">{{ $promo->name }}</h3>
                                    @php $chip = ['active' => 'bg-emerald-100 text-emerald-700', 'scheduled' => 'bg-sky-100 text-sky-700', 'expired' => 'bg-ink-100 text-ink-500', 'disabled' => 'bg-red-100 text-red-600'][$status]; @endphp
                                    <span class="kv-badge {{ $chip }}">{{ ucfirst($status) }}</span>
                                    @if($live && $live->id === $promo->id)
                                        <span class="kv-badge bg-brand-50 text-brand-700"><i class="fas fa-bolt mr-1"></i>Live now</span>
                                    @endif
                                </div>
                                <p class="mt-1.5 text-xs text-ink-500">
                                    {{ $promo->starts_at?->format('M d, Y H:i') }} → {{ $promo->ends_at?->format('M d, Y H:i') }}
                                    <span class="text-ink-400">({{ config('app.timezone') }})</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <a href="{{ route('admin.promotions.coverage', $promo) }}" class="kv-btn-ghost !py-1.5 text-xs"><i class="fas fa-shield-halved"></i> Margin check</a>
                                <form method="POST" action="{{ route('admin.promotions.toggle', $promo) }}">
                                    @csrf @method('PUT')
                                    <button class="kv-btn !py-1.5 text-xs {{ $promo->is_enabled ? 'bg-red-50 text-red-700 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                        {{ $promo->is_enabled ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                            <div class="rounded-xl bg-ink-50 px-3.5 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-400">Facebook</p>
                                <p class="mt-0.5 font-bold text-ink-900">{{ xaf($promo->price('facebook_price') ?? 0) }}</p>
                            </div>
                            <div class="rounded-xl bg-ink-50 px-3.5 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-400">WhatsApp US</p>
                                <p class="mt-0.5 font-bold text-ink-900">{{ xaf($promo->price('whatsapp_us_price') ?? 0) }}</p>
                            </div>
                            <div class="rounded-xl bg-ink-50 px-3.5 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-400">WA discount</p>
                                <p class="mt-0.5 font-bold text-ink-900">
                                    {{ $promo->price('whatsapp_discount') !== null ? '−' . xaf($promo->price('whatsapp_discount')) : '—' }}
                                </p>
                            </div>
                            <div class="rounded-xl bg-ink-50 px-3.5 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-400">Telegram</p>
                                <p class="mt-0.5 font-bold text-ink-900">{{ xaf($promo->price('telegram_price') ?? 0) }}</p>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-ink-100 grid grid-cols-2 sm:grid-cols-5 gap-3 text-sm">
                            <div><p class="text-xs text-ink-400">Promo orders</p><p class="font-bold text-ink-900">{{ $promo->stats['orders'] }}</p></div>
                            <div><p class="text-xs text-ink-400">Revenue</p><p class="font-bold text-ink-900">{{ xaf($promo->stats['revenue']) }}</p></div>
                            <div><p class="text-xs text-ink-400">Facebook</p><p class="font-semibold text-ink-700">{{ $promo->stats['facebook'] }}</p></div>
                            <div><p class="text-xs text-ink-400">WhatsApp</p><p class="font-semibold text-ink-700">{{ $promo->stats['whatsapp'] }}</p></div>
                            <div><p class="text-xs text-ink-400">Telegram</p><p class="font-semibold text-ink-700">{{ $promo->stats['telegram'] }}</p></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-admin-layout>
