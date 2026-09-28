<x-admin-layout>
    <x-slot name="title">Countries</x-slot>
    <x-slot name="header">Countries</x-slot>

    <div class="space-y-5" x-data="{ query: '' }">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
            <div class="relative flex-1 max-w-sm">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 text-xs"></i>
                <input type="text" x-model="query" placeholder="Search countries…" class="kv-input !pl-9">
            </div>
            <a href="{{ route('admin.countries.create') }}" class="kv-btn-primary shrink-0"><i class="fas fa-plus"></i> Add country</a>
        </div>

        <div class="kv-card overflow-hidden">
            @if($countries->isEmpty())
                <x-empty-state icon="fa-earth-americas" title="No countries" />
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach($countries as $country)
                        <li class="flex items-center gap-4 px-5 sm:px-6 py-3.5 hover:bg-ink-50/60 transition"
                            x-show="!query || '{{ strtolower(addslashes($country->name)) }}'.includes(query.toLowerCase())">
                            <span class="text-2xl">{{ countryFlag($country->code) }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-ink-900 text-sm">{{ $country->name }}</p>
                                <p class="text-xs text-ink-400 font-mono">{{ $country->code }} · {{ $country->dial_code }}</p>
                            </div>
                            <x-status-badge :status="$country->is_active ? 'active' : 'inactive'" />
                            <div class="flex items-center gap-1.5">
                                <a href="{{ route('admin.countries.show', $country) }}" class="p-2 text-ink-400 hover:text-brand-600 transition" title="View"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('admin.countries.edit', $country) }}" class="p-2 text-ink-400 hover:text-brand-600 transition" title="Edit"><i class="fas fa-pen"></i></a>
                                <form method="POST" action="{{ route('admin.countries.toggle-status', $country) }}">
                                    @csrf @method('PUT')
                                    <button type="submit" class="p-2 {{ $country->is_active ? 'text-ink-400 hover:text-red-600' : 'text-ink-400 hover:text-emerald-600' }} transition"
                                            title="{{ $country->is_active ? 'Deactivate' : 'Activate' }}">
                                        <i class="fas {{ $country->is_active ? 'fa-ban' : 'fa-circle-check' }}"></i>
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($countries->hasPages())
            <div>{{ $countries->links() }}</div>
        @endif
    </div>
</x-admin-layout>
