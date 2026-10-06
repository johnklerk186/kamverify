<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' · ' : '' }}{{ config('app.name', 'KamVerify') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#0d9488">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="KamVerify">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-ink-900">
<div class="min-h-screen flex">

    {{-- Brand panel (desktop) --}}
    <div class="hidden lg:flex lg:w-[42%] xl:w-[38%] relative overflow-hidden bg-ink-900">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-700 via-ink-900 to-ink-950"></div>
        <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-brand-500/20 blur-3xl"></div>
        <div class="absolute -bottom-32 -left-24 w-96 h-96 rounded-full bg-cyan-500/10 blur-3xl"></div>
        <div class="relative flex flex-col justify-between w-full p-12">
            <a href="{{ url('/') }"><x-kv-logo size="w-10 h-10" :light="true" /></a>
            <div>
                <h2 class="text-3xl font-extrabold text-white leading-tight">
                    Virtual numbers,<br>private SMS verification.
                </h2>
                <p class="mt-4 text-ink-300 max-w-sm">
                    Buy a number, receive your OTP code in your dashboard, and keep your personal phone private.
                </p>
                <ul class="mt-8 space-y-3 text-sm text-ink-200">
                    <li class="flex items-center gap-3"><i class="fas fa-check-circle text-brand-400"></i> Pay only for successful activations</li>
                    <li class="flex items-center gap-3"><i class="fas fa-check-circle text-brand-400"></i> Automatic refunds on failed orders</li>
                    <li class="flex items-center gap-3"><i class="fas fa-check-circle text-brand-400"></i> Secure wallet & transparent pricing</li>
                </ul>
            </div>
            <p class="text-xs text-ink-500">&copy; {{ date('Y') }} KamVerify</p>
        </div>
    </div>

    {{-- Form column --}}
    <div class="flex-1 flex flex-col bg-ink-50">
        <div class="flex items-center justify-between p-5 lg:hidden">
            <a href="{{ url('/') }}"><x-kv-logo size="w-8 h-8" /></a>
            @if(!request()->routeIs('login'))
                <a href="{{ route('login') }}" class="text-sm font-semibold text-brand-600">Log in</a>
            @else
                <a href="{{ route('register') }}" class="text-sm font-semibold text-brand-600">Create account</a>
            @endif
        </div>

        <div class="flex-1 flex items-center justify-center px-4 sm:px-8 pb-12">
            <div class="w-full max-w-md">
                <div class="kv-card p-6 sm:p-8">
                    {{ $slot }}
                </div>
                <p class="mt-6 text-center text-xs text-ink-400">
                    <a href="{{ route('pages.terms') }}" class="hover:text-ink-600">Terms</a> ·
                    <a href="{{ route('pages.privacy') }}" class="hover:text-ink-600">Privacy</a> ·
                    <a href="{{ route('pages.refund') }}" class="hover:text-ink-600">Refund policy</a>
                </p>
            </div>
        </div>
    </div>
</div>

<x-flash-messages />
<x-smartsupp />
</body>
</html>
