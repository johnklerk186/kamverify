<x-admin-layout>
    <x-slot name="title">{{ $provider->name }}</x-slot>
    <x-slot name="header">Provider settings</x-slot>

    <div class="max-w-3xl space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.providers.index') }}" class="w-9 h-9 rounded-xl border border-ink-200 bg-white grid place-items-center text-ink-500 hover:text-ink-800 transition">
                <i class="fas fa-arrow-left text-sm"></i>
            </a>
            <div>
                <h1 class="text-xl font-extrabold text-ink-900">{{ $provider->name }}</h1>
                <p class="text-xs text-ink-500 font-mono">{{ $provider->slug }}</p>
            </div>
        </div>

        {{-- Credentials --}}
        <div class="kv-card p-6" x-data="{ show: false }">
            <h2 class="font-bold text-ink-900 text-sm">Connection</h2>
            <p class="mt-1 text-xs text-ink-500">Stored credentials are never displayed in full. Leave the key blank to keep the current value.</p>

            <form method="POST" action="{{ route('admin.providers.update', $provider) }}" class="mt-5 space-y-5">
                @csrf @method('PUT')

                <div>
                    <label class="kv-label">Provider name</label>
                    <input type="text" name="name" value="{{ old('name', $provider->name) }}" required class="kv-input">
                    @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="kv-label">Base URL</label>
                    <input type="url" name="base_url" value="{{ old('base_url', $provider->base_url) }}" class="kv-input" placeholder="https://api.provider.com">
                    @error('base_url')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="kv-label">API key</label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'" name="api_key" class="kv-input pr-11" placeholder="{{ $provider->api_key ? '•••••••••••• (set)' : 'Not configured' }}" autocomplete="off">
                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3.5 text-ink-400 hover:text-ink-600" tabindex="-1">
                            <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                    <p class="mt-1.5 text-xs text-ink-400">{{ $provider->api_key ? 'A key is currently stored.' : 'No key stored — provider runs in sandbox mode.' }}</p>
                    @error('api_key')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <label class="flex items-center gap-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $provider->is_active))
                           class="w-4 h-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm font-medium text-ink-700">Provider enabled</span>
                </label>

                <button type="submit" class="kv-btn-primary"><i class="fas fa-check"></i> Save provider</button>
            </form>
        </div>

        {{-- Live status + activation stats --}}
        <div class="kv-card p-6">
            <h2 class="font-bold text-ink-900 text-sm">Operations</h2>
            <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">Balance</dt>
                    <dd class="mt-1 text-lg font-extrabold text-ink-900">
                        {{ $liveBalance !== null ? number_format($liveBalance, 2) . ' USD' : '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">Successful</dt>
                    <dd class="mt-1 text-lg font-extrabold text-emerald-600">{{ $stats['successful'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">Active</dt>
                    <dd class="mt-1 text-lg font-extrabold text-amber-600">{{ $stats['active'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">Failed</dt>
                    <dd class="mt-1 text-lg font-extrabold text-red-500">{{ $stats['failed'] }}</dd>
                </div>
            </dl>
            <p class="mt-4 text-xs text-ink-400">
                Mode: <span class="font-semibold text-ink-600">{{ config('services.hero_sms.mode') === 'production' || !config('services.hero_sms.use_mock') ? 'production' : 'mock' }}</span>
                · Last request: <span class="font-mono">{{ $provider->config['last_request_at'] ?? 'never' }}</span>
                · Last error: <span class="font-mono text-red-500">{{ $provider->config['last_error'] ?? 'none' }}</span>
            </p>
        </div>

        {{-- Live catalog — reference for correct mapping codes --}}
        @if(!empty($catalog['countries']) || !empty($catalog['services']))
            <div class="kv-card p-6" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="w-full flex items-center justify-between">
                    <h2 class="font-bold text-ink-900 text-sm">Live provider catalog <span class="text-ink-400 font-normal">(mapping reference)</span></h2>
                    <i class="fas fa-chevron-down text-ink-400 transition" :class="open ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open" x-cloak class="mt-4 grid sm:grid-cols-2 gap-5">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-500 mb-2">Country IDs (use in country mappings)</p>
                        <ul class="max-h-56 overflow-y-auto divide-y divide-ink-100 text-xs font-mono">
                            @foreach($catalog['countries'] as $c)
                                <li class="py-1.5 flex justify-between gap-2"><span class="text-ink-700">{{ $c['name'] }}</span><span class="text-ink-400">{{ $c['id'] ?? $c['code'] }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-500 mb-2">Service codes (use in service mappings)</p>
                        <ul class="max-h-56 overflow-y-auto divide-y divide-ink-100 text-xs font-mono">
                            @foreach($catalog['services'] as $s)
                                <li class="py-1.5 flex justify-between gap-2"><span class="text-ink-700">{{ $s['name'] }}</span><span class="text-ink-400">{{ $s['slug'] }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- Mappings --}}
        <div class="grid sm:grid-cols-2 gap-5">
            <div class="kv-card overflow-hidden">
                <div class="px-5 py-4 border-b border-ink-100">
                    <h2 class="font-bold text-ink-900 text-sm">Service mappings ({{ $provider->providerServices->count() }})</h2>
                </div>
                @if($provider->providerServices->isEmpty())
                    <div class="py-8 text-center text-sm text-ink-400">No services mapped</div>
                @else
                    <ul class="divide-y divide-ink-100 max-h-80 overflow-y-auto">
                        @foreach($provider->providerServices as $ps)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-ink-900 truncate">{{ $ps->service->name ?? '—' }}</p>
                                    <p class="text-[11px] text-ink-400 font-mono">{{ $ps->provider_service_code }} · {{ number_format((float) $ps->cost, 2) }} USD cost</p>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <form method="POST" action="{{ route('admin.providers.services.toggle', [$provider, $ps]) }}">
                                        @csrf @method('PUT')
                                        <button type="submit" title="{{ $ps->is_active ? 'Deactivate' : 'Activate' }}"
                                                class="p-1.5 rounded-lg text-xs {{ $ps->is_active ? 'text-amber-600 hover:bg-amber-50' : 'text-emerald-600 hover:bg-emerald-50' }}">
                                            <i class="fas {{ $ps->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.providers.services.destroy', [$provider, $ps]) }}" onsubmit="return confirm('Remove this service mapping?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Remove" class="p-1.5 rounded-lg text-xs text-red-500 hover:bg-red-50"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('admin.providers.services.store', $provider) }}" class="px-5 py-4 border-t border-ink-100 flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="flex-1 min-w-[8rem]">
                        <label class="block text-[10px] font-semibold uppercase text-ink-500 mb-1">Service</label>
                        <select name="service_id" class="kv-input !py-1.5 text-xs w-full" required>
                            @foreach($services as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-24">
                        <label class="block text-[10px] font-semibold uppercase text-ink-500 mb-1">Code</label>
                        <input name="provider_service_code" class="kv-input !py-1.5 text-xs w-full" placeholder="wa" required>
                    </div>
                    <div class="w-24">
                        <label class="block text-[10px] font-semibold uppercase text-ink-500 mb-1">Cost $</label>
                        <input name="cost" type="number" step="0.01" min="0" class="kv-input !py-1.5 text-xs w-full" placeholder="0.50" required>
                    </div>
                    <button type="submit" class="kv-btn-secondary !py-1.5 text-xs shrink-0"><i class="fas fa-plus"></i></button>
                </form>
            </div>

            <div class="kv-card overflow-hidden">
                <div class="px-5 py-4 border-b border-ink-100">
                    <h2 class="font-bold text-ink-900 text-sm">Country mappings ({{ $provider->providerCountries->count() }})</h2>
                </div>
                @if($provider->providerCountries->isEmpty())
                    <div class="py-8 text-center text-sm text-ink-400">No countries mapped</div>
                @else
                    <ul class="divide-y divide-ink-100 max-h-80 overflow-y-auto">
                        @foreach($provider->providerCountries as $pc)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span>{{ countryFlag($pc->country->code ?? null) }}</span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-ink-900 truncate">{{ $pc->country->name ?? '—' }}</p>
                                        <p class="text-[11px] text-ink-400 font-mono">{{ $pc->provider_country_code }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <form method="POST" action="{{ route('admin.providers.countries.toggle', [$provider, $pc]) }}">
                                        @csrf @method('PUT')
                                        <button type="submit" title="{{ $pc->is_active ? 'Deactivate' : 'Activate' }}"
                                                class="p-1.5 rounded-lg text-xs {{ $pc->is_active ? 'text-amber-600 hover:bg-amber-50' : 'text-emerald-600 hover:bg-emerald-50' }}">
                                            <i class="fas {{ $pc->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.providers.countries.destroy', [$provider, $pc]) }}" onsubmit="return confirm('Remove this country mapping?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Remove" class="p-1.5 rounded-lg text-xs text-red-500 hover:bg-red-50"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('admin.providers.countries.store', $provider) }}" class="px-5 py-4 border-t border-ink-100 flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="flex-1 min-w-[8rem]">
                        <label class="block text-[10px] font-semibold uppercase text-ink-500 mb-1">Country</label>
                        <select name="country_id" class="kv-input !py-1.5 text-xs w-full" required>
                            @foreach($countries as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-24">
                        <label class="block text-[10px] font-semibold uppercase text-ink-500 mb-1">Code</label>
                        <input name="provider_country_code" class="kv-input !py-1.5 text-xs w-full" placeholder="6" required>
                    </div>
                    <button type="submit" class="kv-btn-secondary !py-1.5 text-xs shrink-0"><i class="fas fa-plus"></i></button>
                </form>
            </div>
        </div>

        {{-- Recent provider API calls — no credentials, just metadata --}}
        <div class="kv-card overflow-hidden">
            <div class="px-5 py-4 border-b border-ink-100">
                <h2 class="font-bold text-ink-900 text-sm">Recent API activity</h2>
            </div>
            @if($recentLogs->isEmpty())
                <div class="py-8 text-center text-sm text-ink-400">No provider calls logged yet</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-left text-[10px] uppercase tracking-wider text-ink-500 border-b border-ink-100">
                                <th class="px-5 py-2.5">Action</th>
                                <th class="px-5 py-2.5">Activation</th>
                                <th class="px-5 py-2.5">Status</th>
                                <th class="px-5 py-2.5">Error</th>
                                <th class="px-5 py-2.5">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach($recentLogs as $log)
                                <tr>
                                    <td class="px-5 py-2.5 font-mono">{{ $log->action }}</td>
                                    <td class="px-5 py-2.5 font-mono text-ink-500">{{ $log->activation_id ?? '—' }}</td>
                                    <td class="px-5 py-2.5">
                                        <x-status-badge :status="$log->status === 'success' ? 'active' : 'inactive'" />
                                    </td>
                                    <td class="px-5 py-2.5 font-mono text-red-500">{{ $log->error_code ?? '—' }}</td>
                                    <td class="px-5 py-2.5 text-ink-400 whitespace-nowrap">{{ $log->created_at->format('M d H:i:s') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
