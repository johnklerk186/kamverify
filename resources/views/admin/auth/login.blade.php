<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin sign in · {{ config('app.name', 'KamVerify') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-ink-950 text-ink-100 min-h-screen flex flex-col">

    <div class="flex-1 flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md" x-data="{ show: false, loading: false }">

            <div class="text-center mb-8">
                <a href="{{ route('home') }}" class="inline-block"><x-kv-logo size="w-12 h-12" :light="true" /></a>
                <h1 class="mt-5 text-xl font-extrabold text-white tracking-tight">KamVerify Admin</h1>
                <p class="mt-1 text-sm text-ink-400 flex items-center justify-center gap-1.5">
                    <i class="fas fa-shield-halved text-brand-400 text-xs"></i> Restricted access — administrators only
                </p>
            </div>

            <div class="rounded-2xl bg-ink-900/80 border border-white/10 shadow-pop p-6 sm:p-8 backdrop-blur">
                <form method="POST" action="{{ route('admin.login.store') }}" @submit="loading = true" class="space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-ink-300 mb-1.5">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                               class="block w-full rounded-xl bg-white/5 border-white/10 text-white placeholder:text-ink-500 shadow-sm text-sm focus:border-brand-500 focus:ring-brand-500"
                               placeholder="admin@kamverify.com">
                        @error('email')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-ink-300 mb-1.5">Password</label>
                        <div class="relative">
                            <input id="password" :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password"
                                   class="block w-full rounded-xl bg-white/5 border-white/10 text-white placeholder:text-ink-500 shadow-sm text-sm pr-11 focus:border-brand-500 focus:ring-brand-500"
                                   placeholder="••••••••">
                            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-500 hover:text-ink-300 p-1"
                                    :aria-label="show ? 'Hide password' : 'Show password'">
                                <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                        @error('password')<p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-white/20 bg-white/5 text-brand-600 focus:ring-brand-500">
                        <span class="text-sm text-ink-400">Remember me</span>
                    </label>

                    <button type="submit" :disabled="loading"
                            class="kv-btn-primary w-full h-11 !text-[15px]">
                        <span x-show="!loading"><i class="fas fa-lock"></i> Sign in to admin</span>
                        <span x-show="loading" x-cloak><i class="fas fa-circle-notch fa-spin"></i> Signing in…</span>
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-xs text-ink-500">
                Customer account?
                <a href="{{ route('login') }}" class="font-semibold text-brand-400 hover:text-brand-300">Sign in to KamVerify</a>
            </p>
        </div>
    </div>

    <x-flash-messages />
</body>
</html>
