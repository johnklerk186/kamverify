<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' · ' : '' }}Admin · {{ config('app.name', 'KamVerify') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-ink-50 text-ink-900">
@php
    $sections = [
        'Overview' => [
            ['route' => 'admin.dashboard',            'pattern' => 'admin.dashboard',      'icon' => 'fa-gauge-high',        'label' => 'Dashboard'],
            ['route' => 'admin.analytics.index',      'pattern' => 'admin.analytics.*',    'icon' => 'fa-chart-pie',         'label' => 'Analytics'],
        ],
        'Catalog' => [
            ['route' => 'admin.orders.index',         'pattern' => 'admin.orders.*',       'icon' => 'fa-rectangle-list',    'label' => 'Orders'],
            ['route' => 'admin.services.index',       'pattern' => 'admin.services.*',     'icon' => 'fa-mobile-screen',     'label' => 'Services'],
            ['route' => 'admin.countries.index',      'pattern' => 'admin.countries.*',    'icon' => 'fa-earth-americas',    'label' => 'Countries'],
            ['route' => 'admin.service-countries.index', 'pattern' => 'admin.service-countries.*', 'icon' => 'fa-star',      'label' => 'Service Countries'],
            ['route' => 'admin.providers.index',      'pattern' => 'admin.providers.*',    'icon' => 'fa-server',            'label' => 'Providers'],
            ['route' => 'admin.pricing.index',        'pattern' => 'admin.pricing.*',      'icon' => 'fa-tag',               'label' => 'Pricing'],
        ],
        'Finance' => [
            ['route' => 'admin.payments.index',       'pattern' => 'admin.payments.*',     'icon' => 'fa-credit-card',       'label' => 'Payments'],
            ['route' => 'admin.refunds.index',        'pattern' => 'admin.refunds.*',      'icon' => 'fa-rotate-left',       'label' => 'Refunds'],
            ['route' => 'admin.transactions.index',   'pattern' => 'admin.transactions.*', 'icon' => 'fa-money-bill-transfer','label' => 'Transactions'],
            ['route' => 'admin.reconciliation.index', 'pattern' => 'admin.reconciliation.*','icon' => 'fa-scale-balanced',    'label' => 'Reconciliation'],
            ['route' => 'admin.referrals.index',      'pattern' => 'admin.referrals.*',    'icon' => 'fa-user-plus',         'label' => 'Referrals'],
        ],
        'Users & Support' => [
            ['route' => 'admin.users.index',          'pattern' => 'admin.users.*',        'icon' => 'fa-users',            'label' => 'Users'],
            ['route' => 'admin.support.index',        'pattern' => 'admin.support.*',      'icon' => 'fa-headset',          'label' => 'Support'],
            ['route' => 'admin.notifications.index',  'pattern' => 'admin.notifications.*','icon' => 'fa-bell',             'label' => 'Notifications'],
        ],
        'System' => [
            ['route' => 'admin.settings.index',       'pattern' => 'admin.settings.*',     'icon' => 'fa-gear',             'label' => 'Settings'],
            ['route' => 'admin.audit-logs.index',     'pattern' => 'admin.audit-logs.*',   'icon' => 'fa-clipboard-list',   'label' => 'Audit Logs'],
        ],
    ];
    $user = Auth::user();
@endphp

<div x-data="{ mobileMenu: false }" class="min-h-screen lg:flex">

    {{-- Desktop sidebar --}}
    <aside class="hidden lg:flex lg:flex-col lg:w-64 lg:fixed lg:inset-y-0 bg-ink-900 z-40">
        <div class="h-16 flex items-center justify-between px-5 border-b border-white/10">
            <a href="{{ route('admin.dashboard') }}"><x-kv-logo size="w-8 h-8" :light="true" /></a>
            <span class="kv-badge bg-brand-500/20 text-brand-300">Admin</span>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5">
            @foreach($sections as $section => $links)
                <div>
                    <p class="px-3 mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">{{ $section }}</p>
                    <div class="space-y-0.5">
                        @foreach($links as $link)
                            <a href="{{ route($link['route']) }}"
                               class="kv-admin-link {{ request()->routeIs($link['pattern']) ? 'kv-admin-link-active' : '' }}">
                                <i class="fas {{ $link['icon'] }} w-5 text-center"></i>{{ $link['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="p-4 border-t border-white/10 space-y-0.5">
            <a href="{{ route('home') }}" class="kv-admin-link !text-ink-400">
                <i class="fas fa-arrow-left w-5 text-center"></i> View site
            </a>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="kv-admin-link !text-ink-400 w-full">
                    <i class="fas fa-sign-out-alt w-5 text-center"></i> Sign out
                </button>
            </form>
        </div>
    </aside>

    {{-- Mobile slide-over --}}
    <div x-show="mobileMenu" class="fixed inset-0 z-50 lg:hidden" style="display:none">
        <div class="absolute inset-0 bg-ink-900/50" @click="mobileMenu = false"></div>
        <div class="absolute inset-y-0 left-0 w-72 max-w-[85%] bg-ink-900 shadow-pop flex flex-col">
            <div class="h-16 flex items-center justify-between px-5 border-b border-white/10">
                <x-kv-logo size="w-8 h-8" :light="true" />
                <button @click="mobileMenu = false" class="text-ink-400 hover:text-white p-2"><i class="fas fa-times"></i></button>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5">
                @foreach($sections as $section => $links)
                    <div>
                        <p class="px-3 mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-ink-500">{{ $section }}</p>
                        <div class="space-y-0.5">
                            @foreach($links as $link)
                                <a href="{{ route($link['route']) }}"
                                   class="kv-admin-link {{ request()->routeIs($link['pattern']) ? 'kv-admin-link-active' : '' }}">
                                    <i class="fas {{ $link['icon'] }} w-5 text-center"></i>{{ $link['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <a href="{{ route('home') }}" class="kv-admin-link !text-ink-400">
                    <i class="fas fa-arrow-left w-5 text-center"></i> View site
                </a>
                <form method="POST" action="{{ route('admin.logout') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="kv-admin-link !text-ink-400 w-full">
                        <i class="fas fa-sign-out-alt w-5 text-center"></i> Sign out
                    </button>
                </form>
            </nav>
        </div>
    </div>

    {{-- Main column --}}
    <div class="flex-1 min-w-0 lg:ml-64 flex flex-col min-h-screen">
        <header class="sticky top-0 z-30 h-16 bg-white/90 backdrop-blur border-b border-ink-200/70">
            <div class="h-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center gap-3">
                <button @click="mobileMenu = true" class="lg:hidden p-2 -ml-2 text-ink-500 hover:text-ink-800" aria-label="Menu">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <a href="{{ route('admin.dashboard') }}" class="lg:hidden"><x-kv-icon size="w-8 h-8" /></a>

                <div class="flex-1 min-w-0 [&_h1,&_h2,&_h3]:!text-lg [&_h1,&_h2,&_h3]:!font-bold [&_h1,&_h2,&_h3]:!text-ink-900 [&_h1,&_h2,&_h3]:truncate">
                    @isset($header)
                        {{ $header }}
                    @endisset
                </div>

                <span class="kv-badge bg-ink-900 text-white hidden sm:inline-flex"><i class="fas fa-shield-halved mr-1 text-brand-300"></i>Admin</span>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-ink-100 transition-colors">
                            <span class="w-8 h-8 rounded-full bg-ink-800 text-white flex items-center justify-center font-bold text-sm">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </span>
                            <i class="fas fa-chevron-down text-xs text-ink-400 hidden sm:block"></i>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="px-4 py-3 border-b border-ink-100">
                            <p class="text-sm font-semibold text-ink-900 truncate">{{ $user->name }}</p>
                            <p class="text-xs text-ink-500 truncate">{{ $user->email }}</p>
                        </div>
                        <x-dropdown-link :href="route('home')"><i class="fas fa-arrow-left mr-2 w-4 text-center"></i> View site</x-dropdown-link>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('admin.logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                <i class="fas fa-sign-out-alt mr-2 w-4 text-center"></i> Log out
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </header>

        <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6 lg:py-8 bg-ink-50">
            <div class="max-w-7xl mx-auto">
                {{ $slot }}
            </div>
        </main>
    </div>
</div>

<x-flash-messages />
</body>
</html>
