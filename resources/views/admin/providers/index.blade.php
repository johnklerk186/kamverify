<x-admin-layout>
    <x-slot name="title">Providers</x-slot>
    <x-slot name="header">Number providers</x-slot>

    <div class="space-y-5">
        <div class="rounded-xl bg-sky-50 border border-sky-200/70 px-4 py-3 flex items-start gap-3">
            <i class="fas fa-circle-info text-sky-600 mt-0.5"></i>
            <p class="text-sm text-sky-800">
                Providers supply the virtual numbers. Until live API credentials are configured, each provider runs in
                <strong>sandbox mode</strong> and returns simulated data. API keys are stored encrypted-at-rest and never displayed.
            </p>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            @foreach($providers as $provider)
                <div class="kv-card p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3.5">
                            <div class="w-11 h-11 rounded-xl bg-ink-900 text-white grid place-items-center">
                                <i class="fas fa-server"></i>
                            </div>
                            <div>
                                <h2 class="font-bold text-ink-900">{{ $provider->name }}</h2>
                                <p class="text-xs text-ink-400 font-mono">{{ $provider->slug }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($provider->live_status['configured'] ?? false)
                                <span class="kv-badge bg-emerald-100 text-emerald-700"><i class="fas fa-circle-check"></i> Live</span>
                            @else
                                <span class="kv-badge bg-amber-100 text-amber-700"><i class="fas fa-flask"></i> Sandbox</span>
                            @endif
                            <x-status-badge :status="$provider->is_active ? 'active' : 'inactive'" />
                        </div>
                    </div>

                    <dl class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                        <div class="rounded-xl bg-ink-50 px-3 py-3">
                            <dt class="text-[11px] font-medium text-ink-500 uppercase">Balance</dt>
                            <dd class="mt-1 text-sm font-bold text-ink-900">
                                {{ isset($provider->live_status['balance']) ? number_format((float) $provider->live_status['balance'], 2) . ' USD' : '—' }}
                            </dd>
                        </div>
                        <div class="rounded-xl bg-ink-50 px-3 py-3">
                            <dt class="text-[11px] font-medium text-ink-500 uppercase">Orders</dt>
                            <dd class="mt-1 text-sm font-bold text-ink-900">{{ number_format($provider->orders_count) }}</dd>
                        </div>
                        <div class="rounded-xl bg-ink-50 px-3 py-3">
                            <dt class="text-[11px] font-medium text-ink-500 uppercase">Services</dt>
                            <dd class="mt-1 text-sm font-bold text-ink-900">{{ $provider->provider_services_count }}</dd>
                        </div>
                        <div class="rounded-xl bg-ink-50 px-3 py-3">
                            <dt class="text-[11px] font-medium text-ink-500 uppercase">Countries</dt>
                            <dd class="mt-1 text-sm font-bold text-ink-900">{{ $provider->provider_countries_count }}</dd>
                        </div>
                    </dl>

                    @php $cfg = $provider->config ?? []; @endphp
                    <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="rounded-xl bg-ink-50 px-3 py-2.5">
                            <dt class="text-[11px] font-medium text-ink-500 uppercase">Last request</dt>
                            <dd class="mt-0.5 text-xs font-semibold text-ink-800">
                                {{ !empty($cfg['last_request_at']) ? \Carbon\Carbon::parse($cfg['last_request_at'])->diffForHumans().' ('.($cfg['last_operation'] ?? 'api').')' : 'Never' }}
                            </dd>
                        </div>
                        <div class="rounded-xl bg-ink-50 px-3 py-2.5">
                            <dt class="text-[11px] font-medium text-ink-500 uppercase">Last error</dt>
                            <dd class="mt-0.5 text-xs font-semibold {{ !empty($cfg['last_error']) ? 'text-red-600' : 'text-ink-800' }}">
                                {{ !empty($cfg['last_error']) ? $cfg['last_error'].' · '.\Carbon\Carbon::parse($cfg['last_error_at'] ?? $cfg['last_request_at'])->diffForHumans() : 'None recorded' }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-5 flex gap-2">
                        <a href="{{ route('admin.providers.edit', $provider) }}" class="kv-btn-secondary flex-1 !py-2 text-xs">
                            <i class="fas fa-gear"></i> Configure
                        </a>
                        <form method="POST" action="{{ route('admin.providers.toggle', $provider) }}" class="flex-1">
                            @csrf @method('PUT')
                            <button type="submit" class="w-full {{ $provider->is_active ? 'kv-btn-ghost !text-red-600 hover:!bg-red-50' : 'kv-btn-primary' }} !py-2 text-xs">
                                <i class="fas {{ $provider->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                {{ $provider->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-admin-layout>
