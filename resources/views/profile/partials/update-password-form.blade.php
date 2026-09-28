<div class="kv-card p-6 sm:p-8" x-data="{ show: false, loading: false }">
    <h2 class="font-bold text-ink-900">Update password</h2>
    <p class="mt-1 text-sm text-ink-500">Use a long, random password to keep your account secure.</p>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-5" @submit="loading = true">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="kv-label">Current password</label>
            <div class="relative">
                <input id="update_password_current_password" name="current_password" :type="show ? 'text' : 'password'"
                       class="kv-input pr-11" autocomplete="current-password">
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3.5 text-ink-400 hover:text-ink-600" tabindex="-1">
                    <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>
            @if($errors->updatePassword->has('current_password'))
                <p class="mt-1.5 text-xs text-red-600">{{ $errors->updatePassword->first('current_password') }}</p>
            @endif
        </div>

        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label for="update_password_password" class="kv-label">New password</label>
                <input id="update_password_password" name="password" :type="show ? 'text' : 'password'"
                       class="kv-input" autocomplete="new-password">
                @if($errors->updatePassword->has('password'))
                    <p class="mt-1.5 text-xs text-red-600">{{ $errors->updatePassword->first('password') }}</p>
                @endif
            </div>
            <div>
                <label for="update_password_password_confirmation" class="kv-label">Confirm password</label>
                <input id="update_password_password_confirmation" name="password_confirmation" :type="show ? 'text' : 'password'"
                       class="kv-input" autocomplete="new-password">
                @if($errors->updatePassword->has('password_confirmation'))
                    <p class="mt-1.5 text-xs text-red-600">{{ $errors->updatePassword->first('password_confirmation') }}</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="kv-btn-primary" :disabled="loading">
                <span x-show="!loading"><i class="fas fa-key"></i> Update password</span>
                <span x-show="loading" x-cloak><i class="fas fa-circle-notch fa-spin"></i> Updating…</span>
            </button>
            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)"
                   class="text-sm font-medium text-emerald-600"><i class="fas fa-check-circle"></i> Saved.</p>
            @endif
        </div>
    </form>
</div>
