<x-admin-layout>
    <x-slot name="title">Add Service</x-slot>
    <x-slot name="header">Add service</x-slot>

    <div class="max-w-xl">
        <div class="kv-card p-6 sm:p-8">
            <form method="POST" action="{{ route('admin.services.store') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="kv-label">Service name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="kv-input" placeholder="WhatsApp">
                    @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="kv-label">Slug</label>
                    <input type="text" name="slug" value="{{ old('slug') }}" required maxlength="100" class="kv-input lowercase font-mono" placeholder="whatsapp">
                    <p class="mt-1.5 text-xs text-ink-400">Lowercase identifier used for icon mapping and provider lookups.</p>
                    @error('slug')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="kv-label">Icon key <span class="text-ink-400 font-normal">(optional)</span></label>
                    <input type="text" name="icon" value="{{ old('icon') }}" class="kv-input" placeholder="whatsapp">
                    <p class="mt-1.5 text-xs text-ink-400">Matches a known service for the icon (e.g. whatsapp, telegram, google). Defaults to slug.</p>
                    @error('icon')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="kv-label">Description <span class="text-ink-400 font-normal">(optional)</span></label>
                    <textarea name="description" rows="3" class="kv-input">{{ old('description') }}</textarea>
                    @error('description')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="w-4 h-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm font-medium text-ink-700">Available for purchase</span>
                </label>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="kv-btn-primary"><i class="fas fa-check"></i> Create service</button>
                    <a href="{{ route('admin.services.index') }}" class="kv-btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
