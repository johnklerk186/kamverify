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
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="KamVerify">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-ink-50 text-ink-900">
@php
    $user = Auth::user();
    $navLinks = [
        ['route' => 'dashboard',       'pattern' => 'dashboard',    'icon' => 'fa-gauge-high',     'label' => 'Dashboard'],
        ['route' => 'orders.create',   'pattern' => 'orders.create','icon' => 'fa-cart-shopping',  'label' => 'Buy Number'],
        ['route' => 'orders.index',    'pattern' => 'orders.index', 'icon' => 'fa-rectangle-list', 'label' => 'Orders'],
        ['route' => 'orders.index',    'pattern' => 'orders.show',  'icon' => 'fa-rectangle-list', 'label' => 'Orders', 'hidden' => true],
        ['route' => 'wallet.index',    'pattern' => 'wallet.*',     'icon' => 'fa-wallet',         'label' => 'Wallet'],
        ['route' => 'referral.index',  'pattern' => 'referral.*',   'icon' => 'fa-user-plus',      'label' => 'Referrals'],
        ['route' => 'notifications.index', 'pattern' => 'notifications.*', 'icon' => 'fa-bell',    'label' => 'Notifications'],
        ['route' => 'support.index',   'pattern' => 'support.*',    'icon' => 'fa-headset',        'label' => 'Support'],
    ];
    $unread = $user->unreadNotifications()->count();
    $balance = $user->wallet->balance ?? 0;
@endphp

<div x-data="{ mobileMenu: false }" class="min-h-screen lg:flex">

    {{-- ============ DESKTOP SIDEBAR ============ --}}
    <aside class="hidden lg:flex lg:flex-col lg:w-64 lg:fixed lg:inset-y-0 bg-white border-r border-ink-200/70 z-40">
        <div class="h-16 flex items-center px-5 border-b border-ink-200/70">
            <a href="{{ route('dashboard') }}"><x-kv-logo size="w-9 h-9" /></a>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
            @foreach($navLinks as $link)
                @if(!($link['hidden'] ?? false))
                    <a href="{{ route($link['route']) }}"
                       class="kv-sidebar-link {{ request()->routeIs($link['pattern']) ? 'kv-sidebar-link-active' : '' }}">
                        <i class="fas {{ $link['icon'] }} w-5 text-center"></i>
                        <span class="flex-1">{{ $link['label'] }}</span>
                        @if($link['label'] === 'Notifications' && $unread > 0)
                            <span class="kv-badge bg-red-100 text-red-600">{{ $unread }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        </nav>

        <div class="p-3 border-t border-ink-200/70">
            <a href="{{ route('wallet.index') }}" class="block rounded-xl bg-ink-900 text-white p-4 hover:bg-ink-800 transition-colors">
                <p class="text-xs text-ink-300 font-medium">Wallet balance</p>
                <p class="text-lg font-bold mt-0.5">{{ xaf($balance) }}</p>
                <span class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-brand-300">
                    <i class="fas fa-plus-circle"></i> Deposit funds
                </span>
            </a>
            <a href="{{ route('orders.create') }}" class="kv-btn-primary w-full mt-3">
                <i class="fas fa-bolt"></i> Buy a number
            </a>
        </div>
    </aside>

    {{-- ============ MOBILE MENU (slide-over) ============ --}}
    <div x-show="mobileMenu" class="fixed inset-0 z-50 lg:hidden" style="display:none">
        <div class="absolute inset-0 bg-ink-900/50" @click="mobileMenu = false"
             x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>
        <div class="absolute inset-y-0 left-0 w-72 max-w-[85%] bg-white shadow-pop flex flex-col"
             x-transition:enter="transition-transform duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0">
            <div class="h-16 flex items-center justify-between px-5 border-b border-ink-200/70">
                <x-kv-logo size="w-8 h-8" />
                <button @click="mobileMenu = false" class="text-ink-400 hover:text-ink-600 p-2"><i class="fas fa-times"></i></button>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                @foreach($navLinks as $link)
                    @if(!($link['hidden'] ?? false))
                        <a href="{{ route($link['route']) }}"
                           class="kv-sidebar-link {{ request()->routeIs($link['pattern']) ? 'kv-sidebar-link-active' : '' }}">
                            <i class="fas {{ $link['icon'] }} w-5 text-center"></i>
                            <span class="flex-1">{{ $link['label'] }}</span>
                            @if($link['label'] === 'Notifications' && $unread > 0)
                                <span class="kv-badge bg-red-100 text-red-600">{{ $unread }}</span>
                            @endif
                        </a>
                    @endif
                @endforeach
                <a href="{{ route('profile.edit') }}" class="kv-sidebar-link {{ request()->routeIs('profile.*') ? 'kv-sidebar-link-active' : '' }}">
                    <i class="fas fa-user-gear w-5 text-center"></i><span class="flex-1">Profile</span>
                </a>
            </nav>
            <div class="p-4 border-t border-ink-200/70">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-9 h-9 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-sm">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink-900 truncate">{{ $user->name }}</p>
                        <p class="text-xs text-ink-500 truncate">{{ $user->email }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="kv-btn-secondary w-full"><i class="fas fa-sign-out-alt"></i> Log out</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ============ MAIN COLUMN ============ --}}
    <div class="flex-1 min-w-0 lg:ml-64 flex flex-col min-h-screen">

        @if(demoMode())
            <div class="bg-amber-400 text-ink-900 px-4 py-1.5 text-center text-[11px] sm:text-xs font-bold tracking-wide uppercase">
                <i class="fas fa-flask mr-1.5"></i>Demo environment — sample data only, no real transactions
            </div>
        @endif

        {{-- Top bar --}}
        <header class="sticky top-0 z-30 h-16 bg-white/90 backdrop-blur border-b border-ink-200/70">
            <div class="h-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center gap-3">
                <a href="{{ route('dashboard') }}" class="lg:hidden"><x-kv-icon size="w-8 h-8" /></a>

                <div class="flex-1 min-w-0 [&_h1,&_h2,&_h3]:!text-lg [&_h1,&_h2,&_h3]:!font-bold [&_h1,&_h2,&_h3]:!text-ink-900 [&_h1,&_h2,&_h3]:truncate">
                    @isset($header)
                        {{ $header }}
                    @endisset
                </div>

                @if(demoMode())
                    <span class="kv-badge bg-amber-400 text-ink-900 font-bold uppercase tracking-wide"><i class="fas fa-flask mr-1"></i>Demo</span>
                @endif

                <a href="{{ route('wallet.index') }}" class="hidden sm:inline-flex items-center gap-2 rounded-full bg-ink-900 text-white pl-3 pr-4 py-1.5 text-sm font-semibold hover:bg-ink-800 transition-colors">
                    <i class="fas fa-wallet text-brand-300 text-xs"></i>
                    {{ xaf($balance) }}
                </a>

                {{-- Notification bell + live dropdown --}}
                <div class="relative" x-data="notifBell({{ $unread }})" @click.outside="open = false">
                    <button type="button" @click="toggle()" class="relative p-2 text-ink-500 hover:text-ink-800 transition-colors" aria-label="Notifications">
                        <i class="fas fa-bell text-lg"></i>
                        <span x-show="unread > 0" x-cloak
                              class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center"
                              x-text="unread > 9 ? '9+' : unread"></span>
                    </button>

                    <div x-show="open" x-cloak x-transition.opacity.scale.95
                         class="absolute right-0 mt-2 w-[calc(100vw-1.5rem)] max-w-[22rem] sm:w-96 rounded-2xl bg-white border border-ink-200/70 shadow-xl z-50 overflow-hidden">
                        <div class="px-4 py-3 border-b border-ink-100 flex items-center justify-between">
                            <p class="font-bold text-sm text-ink-900">Notifications</p>
                            <button type="button" x-show="unread > 0" @click="markAll()"
                                    class="text-xs font-semibold text-brand-600 hover:text-brand-700">
                                Mark all read
                            </button>
                        </div>

                        <div x-show="pushPrompt" x-cloak class="px-4 py-2.5 bg-brand-50 border-b border-brand-100 flex items-center gap-2.5">
                            <i class="fas fa-bell text-brand-600 text-xs"></i>
                            <p class="flex-1 text-xs text-brand-800">Enable browser notifications for instant updates.</p>
                            <button type="button" @click="enablePush()" class="text-xs font-bold text-brand-700 hover:text-brand-800 shrink-0">Enable</button>
                        </div>

                        <div class="max-h-[22rem] overflow-y-auto divide-y divide-ink-100">
                            <template x-for="n in items" :key="n.id">
                                <a :href="n.action_url || '{{ route('notifications.index') }}'"
                                   @click.prevent="openItem(n)"
                                   class="flex gap-3 px-4 py-3 transition-colors"
                                   :class="n.read ? 'bg-white hover:bg-ink-50' : 'bg-brand-50/50 hover:bg-brand-50'">
                                    <span class="w-8 h-8 rounded-lg grid place-items-center shrink-0 mt-0.5"
                                          :class="n.read ? 'bg-ink-100 text-ink-500' : 'bg-brand-100 text-brand-600'">
                                        <i class="fas text-xs" :class="n.icon"></i>
                                    </span>
                                    <span class="flex-1 min-w-0">
                                        <span class="flex items-center gap-2">
                                            <span class="text-sm font-semibold text-ink-900 truncate" x-text="n.title"></span>
                                            <span x-show="!n.read" class="w-1.5 h-1.5 rounded-full bg-brand-500 shrink-0"></span>
                                        </span>
                                        <span class="block text-xs text-ink-500 mt-0.5 line-clamp-2" x-text="n.message"></span>
                                        <span class="block text-[11px] text-ink-400 mt-1" x-text="n.time"></span>
                                    </span>
                                </a>
                            </template>
                            <div x-show="items.length === 0" class="px-4 py-8 text-center">
                                <i class="fas fa-bell-slash text-ink-200 text-2xl"></i>
                                <p class="mt-2 text-xs text-ink-400">No notifications yet.</p>
                            </div>
                        </div>

                        <a href="{{ route('notifications.index') }}"
                           class="block px-4 py-2.5 text-center text-xs font-bold text-brand-600 hover:bg-ink-50 border-t border-ink-100">
                            View all notifications
                        </a>
                    </div>
                </div>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-ink-100 transition-colors">
                            <span class="w-8 h-8 rounded-full bg-brand-600 text-white flex items-center justify-center font-bold text-sm">
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
                        <x-dropdown-link :href="route('profile.edit')"><i class="fas fa-user-gear mr-2 w-4 text-center"></i> Profile</x-dropdown-link>
                        <x-dropdown-link :href="route('wallet.deposit')"><i class="fas fa-plus-circle mr-2 w-4 text-center"></i> Deposit</x-dropdown-link>
                        <x-dropdown-link :href="route('support.index')"><i class="fas fa-headset mr-2 w-4 text-center"></i> Support</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                <i class="fas fa-sign-out-alt mr-2 w-4 text-center"></i> Log out
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>

                <button @click="mobileMenu = true" class="lg:hidden p-2 text-ink-500 hover:text-ink-800" aria-label="Menu">
                    <i class="fas fa-bars text-lg"></i>
                </button>
            </div>
        </header>

        <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6 lg:py-8 pb-24 lg:pb-10">
            <div class="max-w-7xl mx-auto">
                {{ $slot }}
            </div>
        </main>

        <footer class="hidden lg:block border-t border-ink-200/70 py-4">
            <div class="max-w-7xl mx-auto px-6 flex items-center justify-between text-xs text-ink-400">
                <span>&copy; {{ date('Y') }} KamVerify</span>
                <span class="space-x-4">
                    <a href="{{ route('pages.terms') }}" class="hover:text-ink-600">Terms</a>
                    <a href="{{ route('pages.privacy') }}" class="hover:text-ink-600">Privacy</a>
                    <a href="{{ route('pages.refund') }}" class="hover:text-ink-600">Refunds</a>
                    <a href="{{ route('support.index') }}" class="hover:text-ink-600">Support</a>
                </span>
            </div>
        </footer>
    </div>

    {{-- ============ MOBILE BOTTOM NAV ============ --}}
    <nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-ink-200 pb-[env(safe-area-inset-bottom)]">
        <div class="grid grid-cols-5 h-16">
            <a href="{{ route('dashboard') }}" class="flex flex-col items-center justify-center gap-0.5 text-[11px] font-medium {{ request()->routeIs('dashboard') ? 'text-brand-600' : 'text-ink-500' }}">
                <i class="fas fa-gauge-high text-base"></i>Home
            </a>
            <a href="{{ route('orders.index') }}" class="flex flex-col items-center justify-center gap-0.5 text-[11px] font-medium {{ request()->routeIs('orders.index') || request()->routeIs('orders.show') ? 'text-brand-600' : 'text-ink-500' }}">
                <i class="fas fa-rectangle-list text-base"></i>Orders
            </a>
            <a href="{{ route('orders.create') }}" class="flex flex-col items-center justify-center -mt-5">
                <span class="w-12 h-12 rounded-2xl bg-brand-600 text-white flex items-center justify-center shadow-pop {{ request()->routeIs('orders.create') ? 'ring-2 ring-brand-300' : '' }}">
                    <i class="fas fa-plus text-lg"></i>
                </span>
                <span class="text-[11px] font-medium text-ink-500 mt-0.5">Buy</span>
            </a>
            <a href="{{ route('wallet.index') }}" class="flex flex-col items-center justify-center gap-0.5 text-[11px] font-medium {{ request()->routeIs('wallet.*') ? 'text-brand-600' : 'text-ink-500' }}">
                <i class="fas fa-wallet text-base"></i>Wallet
            </a>
            <a href="{{ route('support.index') }}" class="flex flex-col items-center justify-center gap-0.5 text-[11px] font-medium {{ request()->routeIs('support.*') ? 'text-brand-600' : 'text-ink-500' }}">
                <i class="fas fa-headset text-base"></i>Support
            </a>
        </div>
    </nav>
</div>

<x-flash-messages />

{{-- Temporary service outage notice — auto-opens once per login when
     any storefront service is flagged; re-opened by kv-service-outage
     events whenever the customer clicks a flagged service. --}}
<x-service-outage-modal :auto-open="session()->has('service_outage')" :names="session('service_outage', [])" />

<script>
    // ---- Notification bell dropdown ----
    function notifBell(initialUnread) {
        return {
            open: false,
            unread: initialUnread,
            items: [],
            loaded: false,
            pushPrompt: false,

            toggle() {
                this.open = !this.open;
                if (this.open && !this.loaded) this.refresh();
            },
            async refresh() {
                try {
                    const res = await fetch('{{ route('notifications.feed') }}', { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    this.items = data.notifications;
                    this.unread = data.unread;
                    this.loaded = true;
                } catch (e) { /* silent — feed is best-effort */ }
            },
            async openItem(n) {
                if (!n.read) {
                    fetch('/notifications/' + n.id + '/read', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                    });
                    n.read = true;
                    this.unread = Math.max(0, this.unread - 1);
                }
                window.location.href = n.action_url || '{{ route('notifications.index') }}';
            },
            async markAll() {
                await fetch('{{ route('notifications.read-all') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }
                });
                this.items.forEach(n => n.read = true);
                this.unread = 0;
            },
            async enablePush() {
                if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                    this.pushPrompt = false;
                    return;
                }
                const permission = await Notification.requestPermission();
                this.pushPrompt = false;
                if (permission === 'granted') await subscribePush();
            },
            init() {
                // Offer the push opt-in only where supported and not yet decided.
                this.pushPrompt = ('Notification' in window) && window.Notification.permission === 'default'
                    && '{{ config('services.webpush.public_key') }}' !== '';
            }
        }
    }

    // ---- Web Push subscription (VAPID) ----
    const VAPID_PUBLIC_KEY = '{{ config('services.webpush.public_key') }}';

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        return Uint8Array.from([...rawData].map(c => c.charCodeAt(0)));
    }

    async function subscribePush() {
        try {
            const reg = await navigator.serviceWorker.register('/sw.js');
            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
            });
            const json = sub.toJSON();
            await fetch('{{ route('push.subscribe') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ endpoint: json.endpoint, keys: json.keys, contentEncoding: 'aes128gcm' })
            });
        } catch (e) { /* unsupported browser or subscription failed — in-app centre still works */ }
    }

    // Auto-renew subscriptions for users who already granted permission.
    if (VAPID_PUBLIC_KEY && 'serviceWorker' in navigator && 'PushManager' in window
        && 'Notification' in window && window.Notification.permission === 'granted') {
        subscribePush();
    }
</script>

<x-smartsupp />
</body>
</html>
