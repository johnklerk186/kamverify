<div class="kv-card p-6 sm:p-8" x-data="{ loading: false }">
    <h2 class="font-bold text-ink-900">Profile information</h2>
    <p class="mt-1 text-sm text-ink-500">Update your name and email address.</p>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5" @submit="loading = true">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="kv-label">Name</label>
            <input id="name" name="name" type="text" class="kv-input @error('name') border-red-400 focus:ring-red-500 @enderror"
                   value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
            @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="email" class="kv-label">Email</label>
            <input id="email" name="email" type="email" class="kv-input @error('email') border-red-400 focus:ring-red-500 @enderror"
                   value="{{ old('email', $user->email) }}" required autocomplete="username">
            @error('email')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-3 rounded-xl bg-amber-50 border border-amber-200/70 px-4 py-3">
                    <p class="text-sm text-amber-800">
                        Your email address is unverified.
                        <button form="send-verification" class="font-semibold underline hover:text-amber-900">
                            Resend verification email
                        </button>
                    </p>
                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-1.5 text-sm font-medium text-emerald-700">A new verification link has been sent.</p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="kv-btn-primary" :disabled="loading">
                <span x-show="!loading"><i class="fas fa-check"></i> Save changes</span>
                <span x-show="loading" x-cloak><i class="fas fa-circle-notch fa-spin"></i> Saving…</span>
            </button>
            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)"
                   class="text-sm font-medium text-emerald-600"><i class="fas fa-check-circle"></i> Saved.</p>
            @endif
        </div>
    </form>
</div>
