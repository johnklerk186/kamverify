<x-admin-layout>
    <x-slot name="title">Pricing</x-slot>
    <x-slot name="header">Pricing &amp; margins</x-slot>

    <div class="space-y-6" x-data="{ tab: 'services', countryQuery: '' }">
        <div class="rounded-xl bg-ink-50 border border-ink-200/70 px-4 py-3 text-sm text-ink-600">
            <i class="fas fa-tag text-brand-600 mr-1.5"></i>
            Customer price (XAF) = live provider cost + markup. Each service can use a fixed XAF markup or a percentage; the provider cost varies by country and is never shown to customers.
        </div>

        {{-- Default margin --}}
        <div class="kv-card p-6">
            <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                <div class="flex-1">
                    <h2 class="font-bold text-ink-900 text-sm">Default markup</h2>
                    <p class="mt-1 text-xs text-ink-500">Applied when no service- or country-specific markup is set.</p>
                </div>
                <form method="POST" action="{{ route('admin.pricing.defaults') }}" class="flex flex-wrap items-end gap-3">
                    @csrf @method('PUT')
                    <div>
                        <label class="kv-label !mb-1 text-xs">Type</label>
                        <select name="markup_type" class="kv-input !py-2 w-36">
                            <option value="percentage" @selected($defaults['markup_type'] === 'percentage')>Percentage %</option>
                            <option value="fixed" @selected($defaults['markup_type'] === 'fixed')>Fixed XAF</option>
                        </select>
                    </div>
                    <div>
                        <label class="kv-label !mb-1 text-xs">Value</label>
                        <input type="number" name="markup_value" value="{{ $defaults['markup_value'] }}" step="0.01" min="0" required class="kv-input !py-2 w-28">
                    </div>
                    <button type="submit" class="kv-btn-primary !py-2 text-xs"><i class="fas fa-check"></i> Save</button>
                </form>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-1.5">
            <button @click="tab = 'services'" class="rounded-xl px-4 py-2 text-sm font-semibold transition"
                    :class="tab === 'services' ? 'bg-ink-900 text-white' : 'bg-white border border-ink-200/70 text-ink-600'">
                Services ({{ $services->count() }})
            </button>
            <button @click="tab = 'countries'" class="rounded-xl px-4 py-2 text-sm font-semibold transition"
                    :class="tab === 'countries' ? 'bg-ink-900 text-white' : 'bg-white border border-ink-200/70 text-ink-600'">
                Countries ({{ $countries->count() }})
            </button>
        </div>

        {{-- Services pricing --}}
        <div class="kv-card overflow-hidden" x-show="tab === 'services'">
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-ink-50/70 border-b border-ink-100">
                        <tr>
                            <th class="kv-th">Service</th>
                            <th class="kv-th">Provider cost</th>
                            <th class="kv-th">Markup</th>
                            <th class="kv-th">Mode</th>
                            <th class="kv-th">Customer price</th>
                            <th class="kv-th">Status</th>
                            <th class="kv-th">Updated</th>
                            <th class="kv-th"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach($services as $service)
                            @php [$icon, $color] = serviceIcon($service->slug); @endphp
                            <tr x-data="{ editing: false }">
                                <td class="kv-td">
                                    <div class="flex items-center gap-2.5">
                                        <i class="{{ $icon }} {{ $color }}"></i>
                                        <div>
                                            <span class="font-semibold text-ink-900">{{ $service->name }}</span>
                                            @if($service->customer_enabled)
                                                <span class="ml-1 kv-badge bg-brand-50 text-brand-700">Storefront</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="kv-td">
                                    <span x-show="!editing">{{ $service->provider_cost !== null ? number_format($service->provider_cost, 2) . ' USD' : '—' }}</span>
                                </td>
                                <td class="kv-td">
                                    <span x-show="!editing" class="font-medium {{ $service->markup !== null ? 'text-brand-700' : 'text-ink-400' }}">
                                        {{ $service->markup !== null ? ($service->resolved_mode === 'percentage' ? $service->markup.'%' : xaf($service->markup)) : 'default' }}
                                    </span>
                                    <form x-show="editing" x-cloak method="POST" action="{{ route('admin.pricing.services.update', $service) }}"
                                          onsubmit="return confirm('Save new pricing for {{ $service->name }}? Customer prices update immediately.');"
                                          class="flex items-center gap-2">
                                        @csrf @method('PUT')
                                        <input type="number" name="provider_cost" value="{{ $service->provider_cost }}" step="0.01" min="0" placeholder="Cost (USD)" class="kv-input !py-1.5 !w-24 !text-xs" title="Provider cost">
                                        <input type="number" name="markup" value="{{ $service->markup }}" step="0.01" min="0" placeholder="Markup" class="kv-input !py-1.5 !w-24 !text-xs" title="Markup (blank = default)">
                                        <select name="mode" class="kv-input !py-1.5 !w-28 !text-xs" title="Pricing mode">
                                            <option value="" @selected($service->mode === null)>Default</option>
                                            <option value="fixed" @selected($service->mode === 'fixed')>Fixed XAF</option>
                                            <option value="percentage" @selected($service->mode === 'percentage')>Percent %</option>
                                        </select>
                                        <button type="submit" class="text-emerald-600 hover:text-emerald-700 font-bold text-xs">Save</button>
                                    </form>
                                </td>
                                <td class="kv-td">
                                    <span class="kv-badge {{ $service->resolved_mode === 'percentage' ? 'bg-violet-50 text-violet-700' : 'bg-emerald-50 text-emerald-700' }}">
                                        {{ $service->resolved_mode === 'percentage' ? 'Percentage' : 'Fixed XAF' }}
                                        {{ $service->mode === null ? '· default' : '' }}
                                    </span>
                                </td>
                                <td class="kv-td font-bold text-ink-900">{{ $service->customer_price !== null ? xaf($service->customer_price) : '—' }}</td>
                                <td class="kv-td">
                                    <x-status-badge :status="$service->is_active ? 'active' : 'suspended'" />
                                </td>
                                <td class="kv-td text-xs text-ink-400">{{ $service->updated_at->format('M d, H:i') }}</td>
                                <td class="kv-td text-right">
                                    <button @click="editing = !editing" class="text-brand-600 hover:text-brand-700 font-semibold text-xs" x-text="editing ? 'Cancel' : 'Edit'"></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile --}}
            <ul class="md:hidden divide-y divide-ink-100">
                @foreach($services as $service)
                    @php [$icon, $color] = serviceIcon($service->slug); @endphp
                    <li class="px-5 py-4" x-data="{ editing: false }">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <i class="{{ $icon }} {{ $color }}"></i>
                                <span class="font-semibold text-ink-900 text-sm">{{ $service->name }}</span>
                            </div>
                            <button @click="editing = !editing" class="text-brand-600 font-semibold text-xs" x-text="editing ? 'Cancel' : 'Edit'"></button>
                        </div>
                        <div x-show="!editing" class="mt-2 flex justify-between text-xs text-ink-500">
                            <span>Cost: {{ $service->provider_cost !== null ? number_format($service->provider_cost, 2) . ' USD' : '—' }}</span>
                            <span>Markup: {{ $service->markup !== null ? ($service->resolved_mode === 'percentage' ? $service->markup.'%' : xaf($service->markup)) : 'default' }}</span>
                            <span class="font-bold text-ink-900">Price: {{ $service->customer_price !== null ? xaf($service->customer_price) : '—' }}</span>
                        </div>
                        <form x-show="editing" x-cloak method="POST" action="{{ route('admin.pricing.services.update', $service) }}"
                              onsubmit="return confirm('Save new pricing for {{ $service->name }}?');"
                              class="mt-3 flex items-center gap-2">
                            @csrf @method('PUT')
                            <input type="number" name="provider_cost" value="{{ $service->provider_cost }}" step="0.01" min="0" placeholder="Cost (USD)" class="kv-input !py-2 !text-xs">
                            <input type="number" name="markup" value="{{ $service->markup }}" step="0.01" min="0" placeholder="Markup" class="kv-input !py-2 !text-xs">
                            <select name="mode" class="kv-input !py-2 !text-xs">
                                <option value="" @selected($service->mode === null)>Default</option>
                                <option value="fixed" @selected($service->mode === 'fixed')>Fixed XAF</option>
                                <option value="percentage" @selected($service->mode === 'percentage')>Percent %</option>
                            </select>
                            <button type="submit" class="kv-btn-primary !py-2 !px-3 text-xs">Save</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Countries pricing --}}
        <div class="kv-card overflow-hidden" x-show="tab === 'countries'" x-cloak>
            <div class="px-5 py-4 border-b border-ink-100 flex items-center justify-between gap-3">
                <h2 class="font-bold text-ink-900 text-sm">Country markups</h2>
                <div class="relative w-44 sm:w-56">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-ink-400 text-xs"></i>
                    <input type="text" x-model="countryQuery" placeholder="Search…" class="kv-input !pl-8 !py-2 !text-xs">
                </div>
            </div>
            <ul class="divide-y divide-ink-100">
                @foreach($countries as $country)
                    <li class="px-5 py-3.5" x-data="{ editing: false }"
                        x-show="!countryQuery || '{{ strtolower(addslashes($country->name)) }}'.includes(countryQuery.toLowerCase())">
                        <div class="flex items-center gap-3.5">
                            <span class="text-xl">{{ countryFlag($country->code) }}</span>
                            <span class="flex-1 text-sm font-semibold text-ink-900">{{ $country->name }}</span>
                            <span x-show="!editing" class="text-sm {{ $country->markup !== null ? 'font-bold text-brand-700' : 'text-ink-400' }}">
                                {{ $country->markup !== null ? ($defaults['markup_type'] === 'percentage' ? $country->markup.'%' : xaf($country->markup)) : 'default' }}
                            </span>
                            <button @click="editing = !editing" class="text-brand-600 font-semibold text-xs" x-text="editing ? 'Cancel' : 'Edit'"></button>
                        </div>
                        <form x-show="editing" x-cloak method="POST" action="{{ route('admin.pricing.countries.update', $country) }}" class="mt-3 flex items-center gap-2">
                            @csrf @method('PUT')
                            <input type="number" name="markup" value="{{ $country->markup }}" step="0.01" min="0"
                                   placeholder="{{ $defaults['markup_type'] === 'percentage' ? '% markup (blank = default)' : '$ markup (blank = default)' }}"
                                   class="kv-input !py-2 !text-xs flex-1">
                            <button type="submit" class="kv-btn-primary !py-2 !px-3 text-xs">Save</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-admin-layout>
