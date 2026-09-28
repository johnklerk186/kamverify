<x-admin-layout>
    <x-slot name="title">Add Country</x-slot>
    <x-slot name="header">Add country</x-slot>

    <div class="max-w-xl">
        <div class="kv-card p-6 sm:p-8">
            <form method="POST" action="{{ route('admin.countries.store') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="kv-label">Country name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="kv-input" placeholder="Nigeria">
                    @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="kv-label">ISO code</label>
                        <input type="text" name="code" value="{{ old('code') }}" required maxlength="10" class="kv-input uppercase" placeholder="NG">
                        @error('code')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="kv-label">Dial code</label>
                        <input type="text" name="dial_code" value="{{ old('dial_code') }}" required maxlength="10" class="kv-input" placeholder="+234">
                        @error('dial_code')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="kv-label">Flag override <span class="text-ink-400 font-normal">(optional emoji)</span></label>
                    <input type="text" name="flag" value="{{ old('flag') }}" class="kv-input" placeholder="Auto-generated from ISO code">
                    @error('flag')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="w-4 h-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm font-medium text-ink-700">Available for purchase</span>
                </label>
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="kv-btn-primary"><i class="fas fa-check"></i> Create country</button>
                    <a href="{{ route('admin.countries.index') }}" class="kv-btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
