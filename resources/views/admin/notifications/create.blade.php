<x-admin-layout>
    <x-slot name="title">Send Notification</x-slot>
    <x-slot name="header">Send notification</x-slot>

    <div class="max-w-3xl" x-data="{ audience: '{{ old('audience', 'all') }}' }">
        <div class="kv-card p-6 sm:p-8">
            <form method="POST" action="{{ route('admin.notifications.send') }}">
                @csrf

                <span class="kv-label">Recipients</span>
                <div class="grid sm:grid-cols-3 gap-2.5">
                    <label class="flex items-center gap-3 rounded-xl border px-4 py-3.5 cursor-pointer transition"
                           :class="audience === 'all' ? 'border-brand-500 bg-brand-50/60' : 'border-ink-200 hover:border-brand-300'">
                        <input type="radio" name="audience" value="all" x-model="audience" class="text-brand-600 focus:ring-brand-500">
                        <span>
                            <span class="block text-sm font-semibold text-ink-900">All customers</span>
                            <span class="block text-xs text-ink-400">Every registered customer</span>
                        </span>
                    </label>
                    <label class="flex items-center gap-3 rounded-xl border px-4 py-3.5 cursor-pointer transition"
                           :class="audience === 'selected' ? 'border-brand-500 bg-brand-50/60' : 'border-ink-200 hover:border-brand-300'">
                        <input type="radio" name="audience" value="selected" x-model="audience" class="text-brand-600 focus:ring-brand-500">
                        <span>
                            <span class="block text-sm font-semibold text-ink-900">Selected customers</span>
                            <span class="block text-xs text-ink-400">Pick from the list below</span>
                        </span>
                    </label>
                    <label class="flex items-center gap-3 rounded-xl border px-4 py-3.5 cursor-pointer transition"
                           :class="audience === 'one' ? 'border-brand-500 bg-brand-50/60' : 'border-ink-200 hover:border-brand-300'">
                        <input type="radio" name="audience" value="one" x-model="audience" class="text-brand-600 focus:ring-brand-500">
                        <span>
                            <span class="block text-sm font-semibold text-ink-900">One customer</span>
                            <span class="block text-xs text-ink-400">Single recipient</span>
                        </span>
                    </label>
                </div>
                @error('audience')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror

                <div x-show="audience !== 'all'" x-cloak class="mt-5">
                    <label class="kv-label" for="user_ids">Customers</label>
                    <select id="user_ids" name="user_ids[]" multiple size="8" class="kv-input">
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" @selected(collect(old('user_ids'))->contains($c->id))>
                                {{ $c->name }} — {{ $c->email }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-ink-400">Hold Ctrl/Cmd to select multiple customers.</p>
                    @error('user_ids')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mt-5">
                    <label class="kv-label" for="type">Notification type</label>
                    <select id="type" name="type" class="kv-input sm:max-w-xs">
                        <option value="announcement" @selected(old('type') === 'announcement')>Announcement</option>
                        <option value="account" @selected(old('type') === 'account')>Account</option>
                        <option value="security" @selected(old('type') === 'security')>Security</option>
                        <option value="maintenance" @selected(old('type') === 'maintenance')>Maintenance</option>
                    </select>
                    @error('type')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mt-5">
                    <label class="kv-label" for="title">Title</label>
                    <input id="title" type="text" name="title" value="{{ old('title') }}" maxlength="120" required
                           class="kv-input" placeholder="Scheduled Maintenance">
                    @error('title')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mt-5">
                    <label class="kv-label" for="message">Message</label>
                    <textarea id="message" name="message" rows="4" maxlength="1000" required
                              class="kv-input" placeholder="KamVerify will undergo scheduled maintenance tonight from 11:00 PM to 12:00 AM.">{{ old('message') }}</textarea>
                    @error('message')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mt-5">
                    <label class="kv-label" for="link">Destination link <span class="text-ink-400 font-normal">(optional)</span></label>
                    <input id="link" type="url" name="link" value="{{ old('link') }}" maxlength="500"
                           class="kv-input" placeholder="{{ url('/dashboard') }}">
                    @error('link')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="mt-7 flex items-center gap-3">
                    <button type="submit" class="kv-btn-primary"><i class="fas fa-paper-plane"></i> Send notification</button>
                    <a href="{{ route('admin.notifications.index') }}" class="kv-btn-ghost">Cancel</a>
                </div>

                <p class="mt-4 text-xs text-ink-400 flex items-start gap-2">
                    <i class="fas fa-circle-info mt-0.5"></i>
                    The notification is saved to each customer's notification centre and pushed to browsers that granted permission. This action is audit-logged.
                </p>
            </form>
        </div>
    </div>
</x-admin-layout>
