<x-guest-layout>
    <div class="mb-7">
        <h1 class="text-2xl font-extrabold text-ink-900 tracking-tight">Welcome back</h1>
        <p class="mt-1 text-sm text-ink-500">Sign in to your KamVerify account.</p>
    </div>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" x-data="{ show: false, loading: false }" @submit="loading = true">
        @csrf

        <div>
            <label for="email" class="kv-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="kv-input @error('email') border-red-400 focus:ring-red-500 @enderror" placeholder="you@example.com">
            @error('email')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="mt-4">
            <div class="flex items-center justify-between">
                <label for="password" class="kv-label mb-0">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Forgot password?</a>
                @endif
            </div>
            <div class="relative mt-1.5">
                <input id="password" :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password"
                       class="kv-input pr-11 @error('password') border-red-400 focus:ring-red-500 @enderror" placeholder="••••••••">
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3.5 text-ink-400 hover:text-ink-600 transition" tabindex="-1" aria-label="Toggle password visibility">
                    <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                </button>
            </div>
            @error('password')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="mt-4 flex items-center">
            <input id="remember_me" type="checkbox" name="remember" class="w-4 h-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
            <label for="remember_me" class="ml-2 text-sm text-ink-600">Keep me signed in</label>
        </div>

        <button type="submit" class="kv-btn-primary w-full mt-6 h-11" :disabled="loading">
            <span x-show="!loading"><i class="fas fa-arrow-right-to-bracket mr-1.5"></i>Sign in</span>
            <span x-show="loading" x-cloak><i class="fas fa-circle-notch fa-spin mr-1.5"></i>Signing in...</span>
        </button>
    </form>

    <p class="mt-7 text-center text-sm text-ink-500">
        New to KamVerify?
        <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:text-brand-700">Create an account</a>
    </p>
</x-guest-layout>
