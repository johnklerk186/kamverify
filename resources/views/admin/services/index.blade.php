<x-admin-layout>
    <x-slot name="title">Services</x-slot>
    <x-slot name="header">Services</x-slot>

    <div class="space-y-5" x-data="{ query: '' }">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
            <div class="relative flex-1 max-w-sm">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 text-xs"></i>
                <input type="text" x-model="query" placeholder="Search services…" class="kv-input !pl-9">
            </div>
            <a href="{{ route('admin.services.create') }}" class="kv-btn-primary shrink-0"><i class="fas fa-plus"></i> Add service</a>
        </div>

        <div class="kv-card overflow-hidden">
            @if($services->isEmpty())
                <x-empty-state icon="fa-cubes" title="No services" />
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach($services as $service)
                        @php [$icon, $color] = serviceIcon($service->icon ?? $service->slug); @endphp
                        <li class="flex items-center gap-4 px-5 sm:px-6 py-3.5 hover:bg-ink-50/60 transition"
                            x-show="!query || '{{ strtolower(addslashes($service->name)) }}'.includes(query.toLowerCase())">
                            <span class="w-10 h-10 rounded-xl grid place-items-center shrink-0 bg-ink-50">
                                <i class="{{ $icon }} {{ $color }}"></i>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-ink-900 text-sm">{{ $service->name }}</p>
                                <p class="text-xs text-ink-400 font-mono">{{ $service->slug }}</p>
                            </div>
                            <x-status-badge :status="$service->is_active ? 'active' : 'inactive'" />
                            <span class="kv-badge {{ $service->customer_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-ink-100 text-ink-500' }} hidden sm:inline-flex"
                                  title="Customer-facing purchase visibility">
                                {{ $service->customer_enabled ? 'Storefront' : 'Hidden' }}
                            </span>
                            @if($service->temporarily_unavailable)
                                <span class="kv-badge bg-amber-100 text-amber-700" title="Listed but unpurchasable — outage notice shown to customers">
                                    <i class="fas fa-triangle-exclamation mr-1"></i>Outage
                                </span>
                            @endif
                            <div class="flex items-center gap-1.5">
                                <form method="POST" action="{{ route('admin.services.toggle-unavailable', $service) }}">
                                    @csrf @method('PUT')
                                    <button type="submit" class="p-2 text-ink-400 {{ $service->temporarily_unavailable ? 'hover:text-emerald-600' : 'hover:text-amber-600' }} transition"
                                            title="{{ $service->temporarily_unavailable ? 'Restore availability' : 'Mark temporarily unavailable' }}">
                                        <i class="fas {{ $service->temporarily_unavailable ? 'fa-circle-check' : 'fa-triangle-exclamation' }}"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.services.toggle-customer', $service) }}">
                                    @csrf @method('PUT')
                                    <button type="submit" class="p-2 text-ink-400 hover:text-brand-600 transition"
                                            title="{{ $service->customer_enabled ? 'Hide from customers' : 'Show to customers' }}">
                                        <i class="fas {{ $service->customer_enabled ? 'fa-eye-slash' : 'fa-store' }}"></i>
                                    </button>
                                </form>
                                <a href="{{ route('admin.services.show', $service) }}" class="p-2 text-ink-400 hover:text-brand-600 transition" title="View"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('admin.services.edit', $service) }}" class="p-2 text-ink-400 hover:text-brand-600 transition" title="Edit"><i class="fas fa-pen"></i></a>
                                <form method="POST" action="{{ route('admin.services.toggle-status', $service) }}">
                                    @csrf @method('PUT')
                                    <button type="submit" class="p-2 {{ $service->is_active ? 'text-ink-400 hover:text-red-600' : 'text-ink-400 hover:text-emerald-600' }} transition"
                                            title="{{ $service->is_active ? 'Deactivate' : 'Activate' }}">
                                        <i class="fas {{ $service->is_active ? 'fa-ban' : 'fa-circle-check' }}"></i>
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($services->hasPages())
            <div>{{ $services->links() }}</div>
        @endif
    </div>
</x-admin-layout>
