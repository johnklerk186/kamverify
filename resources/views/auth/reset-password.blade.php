<x-guest-layout>
    <div class="mb-7">
        <h1 class="text-2xl font-extrabold text-ink-900 tracking-tight">Choose a new password</h1>
        <p class="mt-1 text-sm text-ink-500">Enter and confirm your new password below.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" x-data="{ show: false, loading: false }" @submit="loading = true">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="kv-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                   class="kv-input @error('email') border-red-400 focus:ring-red-500 @enderror">
            @error('email')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="mt-4">
            <label for="password" class="kv-label">New password</label>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" name="password" required autocomplete="new-password"
                       class="kv-input pr-11 @error('password') border-red-400 focus:ring-red-500 @enderror" placeholder="At least 8 characters">
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3.5 text-ink-400 hover:text-ink-600 transition" tabindex="-1" aria-label="Toggle password visibility">
                    <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>
            @error('password')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="mt-4">
            <label for="password_confirmation" class="kv-label">Confirm new password</label>
            <input id="password_confirmation" :type="show ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password"
                   class="kv-input" placeholder="Repeat your new password">
            @error('password_confirmation')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="kv-btn-primary w-full mt-6 h-11" :disabled="loading">
            <span x-show="!loading"><i class="fas fa-key mr-1.5"></i>Reset password</span>
            <span x-show="loading" x-cloak><i class="fas fa-circle-notch fa-spin mr-1.5"></i>Resetting...</span>
        </button>
    </form>
</x-guest-layout>
