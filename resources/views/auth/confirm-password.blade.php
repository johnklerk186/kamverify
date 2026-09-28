<x-guest-layout>
    <div class="mb-7">
        <h1 class="text-2xl font-extrabold text-ink-900 tracking-tight">Confirm your password</h1>
        <p class="mt-1 text-sm text-ink-500">This is a secure area. Please confirm your password to continue.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" x-data="{ show: false, loading: false }" @submit="loading = true">
        @csrf

        <div>
            <label for="password" class="kv-label">Password</label>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password"
                       class="kv-input pr-11 @error('password') border-red-400 focus:ring-red-500 @enderror" placeholder="••••••••">
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3.5 text-ink-400 hover:text-ink-600 transition" tabindex="-1" aria-label="Toggle password visibility">
                    <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>
            @error('password')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="kv-btn-primary w-full mt-6 h-11" :disabled="loading">
            <span x-show="!loading"><i class="fas fa-shield-halved mr-1.5"></i>Confirm</span>
            <span x-show="loading" x-cloak><i class="fas fa-circle-notch fa-spin mr-1.5"></i>Confirming...</span>
        </button>
    </form>
</x-guest-layout>
