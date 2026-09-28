<x-admin-layout>
    <x-slot name="title">Settings</x-slot>
    <x-slot name="header">System settings</x-slot>

    <div class="space-y-6">
        <div class="rounded-xl bg-amber-50 border border-amber-200/70 px-4 py-3 flex items-start gap-3">
            <i class="fas fa-triangle-exclamation text-amber-600 mt-0.5"></i>
            <p class="text-sm text-amber-800">Changes take effect immediately. Provider API keys and secrets are managed on the <a href="{{ route('admin.providers.index') }}" class="font-semibold underline">Providers</a> page, not here.</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf @method('PUT')

            @foreach($settings as $group => $groupSettings)
                <div class="kv-card overflow-hidden mb-5">
                    <div class="px-5 sm:px-6 py-4 border-b border-ink-100">
                        <h2 class="font-bold text-ink-900 text-sm capitalize">{{ str_replace('_', ' ', $group ?: 'General') }}</h2>
                    </div>
                    <div class="divide-y divide-ink-100">
                        @foreach($groupSettings as $setting)
                            <div class="px-5 sm:px-6 py-4 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-6">
                                <div class="sm:w-72 shrink-0">
                                    <p class="text-sm font-semibold text-ink-900 font-mono">{{ $setting->key }}</p>
                                    @if($setting->description)
                                        <p class="text-xs text-ink-400 mt-0.5">{{ $setting->description }}</p>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    @if($setting->type === 'boolean')
                                        <input type="hidden" name="settings[{{ $loop->parent->index }}-{{ $setting->id }}][key]" value="{{ $setting->key }}">
                                        <select name="settings[{{ $loop->parent->index }}-{{ $setting->id }}][value]" class="kv-input !py-2 sm:max-w-xs">
                                            <option value="1" @selected($setting->value == '1' || $setting->value === true)>Enabled</option>
                                            <option value="0" @selected($setting->value == '0' || $setting->value === false)>Disabled</option>
                                        </select>
                                    @else
                                        <input type="hidden" name="settings[{{ $loop->parent->index }}-{{ $setting->id }}][key]" value="{{ $setting->key }}">
                                        <input type="text" name="settings[{{ $loop->parent->index }}-{{ $setting->id }}][value]"
                                               value="{{ is_array($setting->value) ? json_encode($setting->value) : $setting->value }}"
                                               class="kv-input !py-2 font-mono text-xs">
                                    @endif
                                </div>
                                <span class="kv-badge bg-ink-100 text-ink-500 shrink-0">{{ $setting->type }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <button type="submit" class="kv-btn-primary"><i class="fas fa-check"></i> Save all settings</button>
        </form>

        {{-- Add setting --}}
        <div class="kv-card p-6" x-data="{ open: false }">
            <button @click="open = !open" class="flex items-center gap-2 text-sm font-semibold text-brand-600 hover:text-brand-700">
                <i class="fas" :class="open ? 'fa-minus' : 'fa-plus'"></i> Add new setting
            </button>
            <form x-show="open" x-cloak method="POST" action="{{ route('admin.settings.create') }}" class="mt-5 grid sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                @csrf
                <div class="lg:col-span-2">
                    <label class="kv-label !mb-1 text-xs">Key</label>
                    <input type="text" name="key" required class="kv-input !py-2 font-mono text-xs" placeholder="my_setting_key">
                </div>
                <div class="lg:col-span-2">
                    <label class="kv-label !mb-1 text-xs">Value</label>
                    <input type="text" name="value" required class="kv-input !py-2 font-mono text-xs">
                </div>
                <div>
                    <label class="kv-label !mb-1 text-xs">Type</label>
                    <select name="type" class="kv-input !py-2">
                        @foreach(['string','integer','float','boolean','json'] as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-4">
                    <label class="kv-label !mb-1 text-xs">Group</label>
                    <input type="text" name="group" required class="kv-input !py-2" placeholder="general">
                </div>
                <button type="submit" class="kv-btn-secondary !py-2 text-xs"><i class="fas fa-plus"></i> Create</button>
            </form>
        </div>
    </div>
</x-admin-layout>
