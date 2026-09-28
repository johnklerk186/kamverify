<x-admin-layout>
    <x-slot name="title">Edit {{ $country->name }}</x-slot>
    <x-slot name="header">Edit country</x-slot>

    <div class="max-w-xl">
        <div class="kv-card p-6 sm:p-8" x-data="{ confirm: false }">
            <div class="flex items-center gap-3 mb-6">
                <span class="text-3xl">{{ countryFlag($country->code) }}</span>
                <div>
                    <h1 class="font-bold text-ink-900">{{ $country->name }}</h1>
                    <p class="text-xs text-ink-400 font-mono">{{ $country->code }} · {{ $country->dial_code }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.countries.update', $country) }}" class="space-y-5">
                @csrf @method('PUT')
                <div>
                    <label class="kv-label">Country name</label>
                    <input type="text" name="name" value="{{ old('name', $country->name) }}" required class="kv-input">
                    @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="kv-label">ISO code</label>
                        <input type="text" name="code" value="{{ old('code', $country->code) }}" required maxlength="10" class="kv-input uppercase">
                        @error('code')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="kv-label">Dial code</label>
                        <input type="text" name="dial_code" value="{{ old('dial_code', $country->dial_code) }}" required maxlength="10" class="kv-input">
                        @error('dial_code')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="kv-label">Flag override</label>
                    <input type="text" name="flag" value="{{ old('flag', $country->flag) }}" class="kv-input">
                    @error('flag')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $country->is_active)) class="w-4 h-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm font-medium text-ink-700">Available for purchase</span>
                </label>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="kv-btn-primary"><i class="fas fa-check"></i> Save changes</button>
                    <a href="{{ route('admin.countries.index') }}" class="kv-btn-ghost">Cancel</a>
                </div>
            </form>

            <div class="mt-8 pt-6 border-t border-ink-100">
                <button type="button" x-show="!confirm" @click="confirm = true" class="kv-btn-ghost !text-red-600 hover:!bg-red-50 !py-2 text-xs">
                    <i class="fas fa-trash"></i> Delete country
                </button>
                <form x-show="confirm" x-cloak method="POST" action="{{ route('admin.countries.destroy', $country) }}" class="flex gap-2">
                    @csrf @method('DELETE')
                    <button type="submit" class="kv-btn-danger !py-2 text-xs flex-1">Confirm delete</button>
                    <button type="button" @click="confirm = false" class="kv-btn-secondary !py-2 text-xs flex-1">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
