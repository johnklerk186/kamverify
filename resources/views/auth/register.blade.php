<x-guest-layout>
    <div class="mb-7">
        <h1 class="text-2xl font-extrabold text-ink-900 tracking-tight">Create your account</h1>
        <p class="mt-1 text-sm text-ink-500">Buy virtual numbers and receive SMS in minutes.</p>
    </div>

    @if($referralCode ?? null)
        <div class="mb-5 flex items-center gap-3 rounded-xl bg-brand-50 border border-brand-200/60 px-4 py-3">
            <i class="fas fa-gift text-brand-600"></i>
            <p class="text-sm text-brand-800">Referral code <strong>{{ $referralCode }}</strong> applied — welcome aboard.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" x-data="{ show: false, loading: false }" @submit="loading = true">
        @csrf
        @if($referralCode ?? request('ref'))
            <input type="hidden" name="referral_code" value="{{ $referralCode ?? request('ref') }}">
        @endif

        <div>
            <label for="name" class="kv-label">Full name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                   class="kv-input @error('name') border-red-400 focus:ring-red-500 @enderror" placeholder="Jane Doe">
            @error('name')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="mt-4">
            <label for="email" class="kv-label">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                   class="kv-input @error('email') border-red-400 focus:ring-red-500 @enderror" placeholder="you@example.com">
            @error('email')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="mt-4">
            <label for="password" class="kv-label">Password</label>
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
            <label for="password_confirmation" class="kv-label">Confirm password</label>
            <input id="password_confirmation" :type="show ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password"
                   class="kv-input" placeholder="Repeat your password">
        </div>

        <button type="submit" class="kv-btn-primary w-full mt-6 h-11" :disabled="loading">
            <span x-show="!loading"><i class="fas fa-user-plus mr-1.5"></i>Create account</span>
            <span x-show="loading" x-cloak><i class="fas fa-circle-notch fa-spin mr-1.5"></i>Creating account...</span>
        </button>

        <p class="mt-4 text-center text-xs text-ink-400">
            By registering you agree to our
            <a href="{{ route('pages.terms') }}" class="text-brand-600 hover:text-brand-700">Terms</a> and
            <a href="{{ route('pages.privacy') }}" class="text-brand-600 hover:text-brand-700">Privacy Policy</a>.
        </p>
    </form>

    <p class="mt-7 text-center text-sm text-ink-500">
        Already have an account?
        <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">Sign in</a>
    </p>
</x-guest-layout>
