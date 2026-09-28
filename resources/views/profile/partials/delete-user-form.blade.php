<div class="kv-card p-6 sm:p-8 border-red-200/70" x-data="{ confirm: false, show: false, loading: false }">
    <h2 class="font-bold text-red-700">Delete account</h2>
    <p class="mt-1 text-sm text-ink-500">Permanently delete your account and all associated data. This cannot be undone.</p>

    <button type="button" x-show="!confirm" @click="confirm = true" class="kv-btn-danger mt-5">
        <i class="fas fa-trash"></i> Delete account
    </button>

    <div x-show="confirm" x-cloak x-transition class="mt-5 rounded-xl border border-red-200 bg-red-50 p-5">
        <div class="flex items-start gap-3">
            <i class="fas fa-triangle-exclamation text-red-600 mt-0.5"></i>
            <div>
                <p class="text-sm font-semibold text-red-800">Are you sure?</p>
                <p class="mt-1 text-sm text-red-700">All orders, wallet history, and referrals will be permanently removed. Enter your password to confirm.</p>
            </div>
        </div>

        <form method="post" action="{{ route('profile.destroy') }}" class="mt-4 space-y-4" @submit="loading = true">
            @csrf
            @method('delete')

            <div class="relative">
                <input id="password" name="password" :type="show ? 'text' : 'password'"
                       class="kv-input pr-11" placeholder="Your password">
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3.5 text-ink-400 hover:text-ink-600" tabindex="-1">
                    <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>
            @if($errors->userDeletion->has('password'))
                <p class="text-xs text-red-600">{{ $errors->userDeletion->first('password') }}</p>
            @endif

            <div class="flex gap-2.5">
                <button type="submit" class="kv-btn-danger flex-1" :disabled="loading">
                    <span x-show="!loading"><i class="fas fa-trash"></i> Permanently delete</span>
                    <span x-show="loading" x-cloak><i class="fas fa-circle-notch fa-spin"></i> Deleting…</span>
                </button>
                <button type="button" @click="confirm = false" class="kv-btn-secondary flex-1">Keep account</button>
            </div>
        </form>
    </div>
</div>
