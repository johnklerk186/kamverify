<x-admin-layout>
    <x-slot name="title">Service Countries</x-slot>
    <x-slot name="header">Popular countries by service</x-slot>

    <div class="space-y-6" x-data="{ query: '' }">
        <div class="rounded-xl bg-ink-50 border border-ink-200/70 px-4 py-3 text-sm text-ink-600">
            <i class="fas fa-earth-americas text-brand-600 mr-1.5"></i>
            Control which countries customers can pick for each service, and pin popular countries to the top of the selector. Availability comes from live provider stock — a country with no stock can't be sold regardless of these settings.
        </div>

        {{-- Service tabs --}}
        <div class="flex flex-wrap gap-1.5">
            @foreach($services as $s)
                @php [$icon, $color] = serviceIcon($s->slug); @endphp
                <a href="{{ route('admin.service-countries.index', ['service_id' => $s->id]) }}"
                   class="rounded-xl px-4 py-2 text-sm font-semibold transition flex items-center gap-2
                          {{ $service && $service->id === $s->id ? 'bg-ink-900 text-white' : 'bg-white border border-ink-200/70 text-ink-600' }}">
                    <i class="{{ $icon }} {{ $service && $service->id === $s->id ? '' : $color }}"></i> {{ $s->name }}
                </a>
            @endforeach
        </div>

        @if($service)
            <div class="kv-card overflow-hidden">
                <div class="px-5 py-4 border-b border-ink-100 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-ink-900 text-sm">{{ $service->name }} countries</h2>
                        <p class="text-xs text-ink-400 mt-0.5">{{ $count }} currently available · {{ count($popularIds) }} pinned as popular{{ empty($popularIds) ? ' (auto-promoted by stock)' : '' }}</p>
                    </div>
                    <div class="relative w-44 sm:w-56">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-ink-400 text-xs"></i>
                        <input type="text" x-model="query" placeholder="Search countries…" class="kv-input !pl-8 !py-2 !text-xs">
                    </div>
                </div>

                <ul class="divide-y divide-ink-100">
                    @foreach($countries as $c)
                        @php $popularPos = array_search($c['id'], $popularIds, true); @endphp
                        <li class="px-5 py-3 flex items-center gap-3"
                            x-show="!query || '{{ strtolower(addslashes($c['name'])) }}'.includes(query.toLowerCase()) || '{{ strtolower($c['code']) }}'.includes(query.toLowerCase())">
                            <span class="text-xl">{{ $c['flag'] }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-ink-900 truncate">{{ $c['name'] }}</p>
                                <p class="text-[11px] text-ink-400">
                                    {{ $c['dial_code'] }}
                                    · {{ $c['stock'] === null ? 'stock unverified' : number_format($c['stock']).' in stock' }}
                                </p>
                            </div>

                            @if(!$c['enabled'])
                                <span class="kv-badge bg-red-50 text-red-600">Disabled</span>
                            @endif
                            @if($c['popular'])
                                <span class="kv-badge bg-amber-50 text-amber-700"><i class="fas fa-star text-[9px]"></i> Popular</span>
                            @endif

                            {{-- Popular reorder --}}
                            @if($c['popular'])
                                <div class="flex flex-col">
                                    <form method="POST" action="{{ route('admin.service-countries.update', [$service, $c['id']]) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="action" value="move_up">
                                        <button class="text-ink-400 hover:text-brand-600 text-[10px] leading-none px-1 {{ $popularPos === 0 ? 'opacity-30 pointer-events-none' : '' }}" title="Move up"><i class="fas fa-chevron-up"></i></button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.service-countries.update', [$service, $c['id']]) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="action" value="move_down">
                                        <button class="text-ink-400 hover:text-brand-600 text-[10px] leading-none px-1 {{ $popularPos === count($popularIds) - 1 ? 'opacity-30 pointer-events-none' : '' }}" title="Move down"><i class="fas fa-chevron-down"></i></button>
                                    </form>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('admin.service-countries.update', [$service, $c['id']]) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="action" value="toggle_popular">
                                <button class="kv-btn-ghost !py-1.5 !px-3 text-xs {{ $c['popular'] ? '!text-amber-600' : '' }}">
                                    <i class="{{ $c['popular'] ? 'fas' : 'far' }} fa-star"></i>
                                    {{ $c['popular'] ? 'Unpin' : 'Pin' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.service-countries.update', [$service, $c['id']]) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="action" value="toggle_enabled">
                                <button class="kv-btn-ghost !py-1.5 !px-3 text-xs {{ $c['enabled'] ? '!text-red-600' : '!text-emerald-600' }}">
                                    <i class="fas {{ $c['enabled'] ? 'fa-ban' : 'fa-circle-check' }}"></i>
                                    {{ $c['enabled'] ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>

                @if(empty($countries))
                    <div class="py-10 text-center text-sm text-ink-400">No countries available for {{ $service->name }} right now.</div>
                @endif
            </div>
        @endif
    </div>
</x-admin-layout>
