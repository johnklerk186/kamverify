<x-admin-layout>
    <x-slot name="title">Edit {{ $service->name }}</x-slot>
    <x-slot name="header">Edit service</x-slot>

    <div class="max-w-xl">
        <div class="kv-card p-6 sm:p-8" x-data="{ confirm: false }">
            @php [$icon, $color] = serviceIcon($service->icon ?? $service->slug); @endphp
            <div class="flex items-center gap-3 mb-6">
                <span class="w-12 h-12 rounded-xl grid place-items-center bg-ink-50 text-xl">
                    <i class="{{ $icon }} {{ $color }}"></i>
                </span>
                <div>
                    <h1 class="font-bold text-ink-900">{{ $service->name }}</h1>
                    <p class="text-xs text-ink-400 font-mono">{{ $service->slug }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.services.update', $service) }}" class="space-y-5">
                @csrf @method('PUT')
                <div>
                    <label class="kv-label">Service name</label>
                    <input type="text" name="name" value="{{ old('name', $service->name) }}" required class="kv-input">
                    @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="kv-label">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug', $service->slug) }}" required maxlength="100" class="kv-input lowercase font-mono">
                    @error('slug')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="kv-label">Icon key</label>
                    <input type="text" name="icon" value="{{ old('icon', $service->icon) }}" class="kv-input">
                    @error('icon')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="kv-label">Description</label>
                    <textarea name="description" rows="3" class="kv-input">{{ old('description', $service->description) }}</textarea>
                    @error('description')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="kv-label">Fulfilment provider</label>
                    <select name="fulfilment_provider" class="kv-input">
                        <option value="">Auto — via provider mappings (default)</option>
                        @foreach($providers as $p)
                            <option value="{{ $p->slug }}" @selected(old('fulfilment_provider', $service->provider_mapping['provider'] ?? '') === $p->slug)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-ink-400">Force a specific provider for this service — e.g. Facebook → TextVerified. "Auto" uses the provider_services mappings.</p>
                    @error('fulfilment_provider')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active)) class="w-4 h-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm font-medium text-ink-700">Available for purchase</span>
                </label>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="kv-btn-primary"><i class="fas fa-check"></i> Save changes</button>
                    <a href="{{ route('admin.services.index') }}" class="kv-btn-ghost">Cancel</a>
                </div>
            </form>

            <div class="mt-8 pt-6 border-t border-ink-100">
                <button type="button" x-show="!confirm" @click="confirm = true" class="kv-btn-ghost !text-red-600 hover:!bg-red-50 !py-2 text-xs">
                    <i class="fas fa-trash"></i> Delete service
                </button>
                <form x-show="confirm" x-cloak method="POST" action="{{ route('admin.services.destroy', $service) }}" class="flex gap-2">
                    @csrf @method('DELETE')
                    <button type="submit" class="kv-btn-danger !py-2 text-xs flex-1">Confirm delete</button>
                    <button type="button" @click="confirm = false" class="kv-btn-secondary !py-2 text-xs flex-1">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
