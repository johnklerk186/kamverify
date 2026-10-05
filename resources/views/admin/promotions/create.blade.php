<x-admin-layout>
    <x-slot name="title">New Promotion</x-slot>
    <x-slot name="header">New promotion</x-slot>

    <div class="max-w-2xl space-y-6">
        <div class="rounded-xl bg-ink-50 border border-ink-200/70 px-4 py-3 text-sm text-ink-600">
            <i class="fas fa-circle-info text-brand-600 mr-1.5"></i>
            The promotion runs for <strong>exactly 7 days</strong> from the start time and then expires automatically — normal prices return on their own. Promotional prices are floored at the provider cost, so no combination can sell at a loss.
        </div>

        <form method="POST" action="{{ route('admin.promotions.store') }}" class="kv-card p-6 space-y-5">
            @csrf

            <div>
                <label class="kv-label">Promotion name</label>
                <input type="text" name="name" value="{{ old('name', $defaults['name']) }}" required maxlength="120" class="kv-input">
            </div>

            <div>
                <label class="kv-label">Start date &amp; time ({{ config('app.timezone') }})</label>
                <input type="datetime-local" name="starts_at" value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}" required class="kv-input">
                <p class="mt-1 text-xs text-ink-400">End time is set automatically to start + 7 days.</p>
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="kv-label">Facebook price (XAF)</label>
                    <input type="number" name="facebook_price" value="{{ old('facebook_price', $defaults['facebook_price']) }}" min="1" required class="kv-input">
                </div>
                <div>
                    <label class="kv-label">WhatsApp USA price (XAF)</label>
                    <input type="number" name="whatsapp_us_price" value="{{ old('whatsapp_us_price', $defaults['whatsapp_us_price']) }}" min="1" required class="kv-input">
                </div>
                <div>
                    <label class="kv-label">Telegram price (XAF)</label>
                    <input type="number" name="telegram_price" value="{{ old('telegram_price', $defaults['telegram_price']) }}" min="1" required class="kv-input">
                </div>
            </div>

            <div>
                <label class="kv-label">WhatsApp discount (XAF) — optional</label>
                <input type="number" name="whatsapp_discount" value="{{ old('whatsapp_discount') }}" min="0" class="kv-input" placeholder="Auto-calculated from the live USA WhatsApp price">
                <p class="mt-1 text-xs text-ink-400">Leave blank to compute automatically: normal USA WhatsApp price − promotional USA price. The same absolute discount then applies to every other WhatsApp country.</p>
            </div>

            @if($errors->any())
                <div class="rounded-xl bg-red-50 border border-red-200/70 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="flex items-center gap-3 pt-1">
                <button type="submit" class="kv-btn-primary"><i class="fas fa-bullhorn"></i> Create promotion</button>
                <a href="{{ route('admin.promotions.index') }}" class="kv-btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</x-admin-layout>
