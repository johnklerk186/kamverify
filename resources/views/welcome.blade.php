<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>KamVerify — Virtual Numbers & SMS Verification</title>
    <meta name="description" content="Get virtual phone numbers and receive SMS verification codes online. Pay per activation, automatic refunds, no subscriptions.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white text-ink-900">

<x-public-nav />

{{-- ==================== HERO ==================== --}}
<section class="relative overflow-hidden bg-ink-900">
    <div class="absolute inset-0 bg-gradient-to-br from-brand-800 via-ink-900 to-ink-950"></div>
    <div class="absolute -top-32 -right-32 w-[28rem] h-[28rem] rounded-full bg-brand-500/15 blur-3xl"></div>
    <div class="absolute -bottom-40 -left-32 w-[28rem] h-[28rem] rounded-full bg-cyan-500/10 blur-3xl"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-20 lg:pt-24 lg:pb-28">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 border border-white/10 px-3.5 py-1.5 text-xs font-semibold text-brand-200">
                    <i class="fas fa-bolt"></i> Pay-per-use · No subscription
                </span>
                <h1 class="mt-6 text-4xl sm:text-5xl font-extrabold text-white tracking-tight leading-[1.1]">
                    Get virtual numbers and receive <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-300 to-cyan-300">SMS codes online</span>
                </h1>
                <p class="mt-5 text-lg text-ink-300 max-w-lg">
                    Pick a service, pick a country, and a number is assigned to you automatically. Your OTP appears right in your dashboard — your real phone stays private.
                </p>
                <div class="mt-8 flex flex-wrap gap-4">
                    @auth
                        <a href="{{ route('orders.create') }}" class="kv-btn-primary !px-7 !py-3.5 !text-base">
                            <i class="fas fa-bolt"></i> Buy a number now
                        </a>
                        <a href="{{ route('dashboard') }}" class="kv-btn !px-7 !py-3.5 !text-base bg-white/10 text-white border border-white/20 hover:bg-white/20">
                            Go to dashboard
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="kv-btn-primary !px-7 !py-3.5 !text-base">
                            Get started — it's free
                        </a>
                        <a href="#how-it-works" class="kv-btn !px-7 !py-3.5 !text-base bg-white/10 text-white border border-white/20 hover:bg-white/20">
                            How it works
                        </a>
                    @endauth
                </div>
                <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-ink-400">
                    <span class="inline-flex items-center gap-2"><i class="fas fa-check text-brand-400"></i> Automatic refunds</span>
                    <span class="inline-flex items-center gap-2"><i class="fas fa-check text-brand-400"></i> Live SMS inbox</span>
                    <span class="inline-flex items-center gap-2"><i class="fas fa-check text-brand-400"></i> Wallet-based billing</span>
                </div>
            </div>

            {{-- Quick purchase widget --}}
            <div class="relative">
                <div class="kv-card !shadow-pop p-6 sm:p-7" x-data="quickBuy()">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-lg font-bold text-ink-900">Get a number</h2>
                        <span class="kv-badge bg-brand-50 text-brand-700"><i class="fas fa-bolt mr-1"></i>Automated</span>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label for="qb-service" class="kv-label">Service</label>
                            <div class="relative">
                                <i class="fas fa-mobile-screen absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 text-sm"></i>
                                <select id="qb-service" x-model="serviceId" class="kv-input !pl-10">
                                    <option value="">Choose a service…</option>
                                    @foreach($services as $service)
                                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="qb-country" class="kv-label">Country</label>
                            <div class="relative">
                                <i class="fas fa-earth-americas absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 text-sm"></i>
                                <select id="qb-country" x-model="countryId" class="kv-input !pl-10">
                                    <option value="">Choose a country…</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}">{{ countryFlag($country->code) }} {{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <a :href="url" class="kv-btn-primary w-full !py-3" :class="{ 'opacity-50 pointer-events-none': !serviceId || !countryId }">
                            <i class="fas fa-arrow-right"></i> Continue
                        </a>
                        <p class="text-xs text-ink-400 text-center">
                            @auth
                                You'll see live availability and pricing before paying.
                            @else
                                Free account required — you'll see live pricing before paying.
                            @endauth
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ==================== REAL STATS ==================== --}}
<section class="border-b border-ink-200/70 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <dl class="grid grid-cols-2 lg:grid-cols-4 gap-6 text-center">
            <div>
                <dt class="text-3xl font-extrabold text-ink-900">{{ $stats['countries'] }}</dt>
                <dd class="text-sm text-ink-500 mt-1">Countries supported</dd>
            </div>
            <div>
                <dt class="text-3xl font-extrabold text-ink-900">{{ $stats['services'] }}</dt>
                <dd class="text-sm text-ink-500 mt-1">Supported services</dd>
            </div>
            <div>
                <dt class="text-3xl font-extrabold text-ink-900">{{ $stats['orders_completed'] }}</dt>
                <dd class="text-sm text-ink-500 mt-1">Verifications delivered</dd>
            </div>
            <div>
                <dt class="text-3xl font-extrabold text-ink-900">{{ $stats['avg_delivery'] }}</dt>
                <dd class="text-sm text-ink-500 mt-1">Avg. code delivery</dd>
            </div>
        </dl>
    </div>
</section>

{{-- ==================== SERVICES ==================== --}}
<section id="services" class="py-16 lg:py-20 bg-ink-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <h2 class="text-3xl font-extrabold text-ink-900">Verify the apps you actually use</h2>
            <p class="mt-3 text-ink-500">Receive SMS codes for the most popular platforms — catalog updates as providers add coverage.</p>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach($services as $service)
                @php [$icon, $color] = serviceIcon($service->icon); @endphp
                <div class="kv-card p-5 flex items-center gap-3.5 hover:border-brand-300 transition-colors">
                    <div class="w-11 h-11 rounded-xl bg-ink-50 flex items-center justify-center shrink-0">
                        <i class="{{ $icon }} text-xl {{ $color }}"></i>
                    </div>
                    <span class="font-semibold text-ink-900 text-sm">{{ $service->name }}</span>
                </div>
            @endforeach
        </div>
        @guest
            <p class="text-center mt-8">
                <a href="{{ route('register') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700">
                    Create an account to see all services <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </p>
        @endguest
    </div>
</section>

{{-- ==================== COUNTRIES ==================== --}}
<section id="countries" class="py-16 lg:py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <h2 class="text-3xl font-extrabold text-ink-900">Numbers from {{ $stats['countries'] }} countries</h2>
            <p class="mt-3 text-ink-500">Local numbers across every region — availability and pricing shown live at checkout.</p>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            @foreach($countries as $country)
                <div class="kv-card !rounded-xl px-4 py-3.5 flex items-center gap-3">
                    <span class="text-2xl leading-none">{{ countryFlag($country->code) }}</span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink-900 truncate">{{ $country->name }}</p>
                        <p class="text-xs text-ink-400">{{ $country->dial_code }}</p>
                    </div>
                </div>
            @endforeach
        </div>
        @if($stats['countries'] > $countries->count())
            <p class="text-center text-sm text-ink-400 mt-6">+ {{ $stats['countries'] - $countries->count() }} more countries available after sign-in</p>
        @endif
    </div>
</section>

{{-- ==================== HOW IT WORKS ==================== --}}
<section id="how-it-works" class="py-16 lg:py-20 bg-ink-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <h2 class="text-3xl font-extrabold text-ink-900">How KamVerify works</h2>
            <p class="mt-3 text-ink-500">Four steps from sign-up to a working verification code.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @php
                $steps = [
                    ['icon' => 'fa-wallet', 'title' => 'Fund your wallet', 'text' => 'Create a free account and top up your balance with a deposit.'],
                    ['icon' => 'fa-earth-americas', 'title' => 'Pick service & country', 'text' => 'Choose the app to verify and the country you want a number from.'],
                    ['icon' => 'fa-phone', 'title' => 'Get your number', 'text' => 'See the exact price, confirm, and a number is assigned automatically.'],
                    ['icon' => 'fa-comment-sms', 'title' => 'Receive the code', 'text' => 'Your OTP appears in your dashboard — copy it and you\'re done.'],
                ];
            @endphp
            @foreach($steps as $i => $step)
                <div class="kv-card p-6 relative">
                    <span class="absolute top-5 right-5 text-4xl font-extrabold text-ink-100">{{ $i + 1 }}</span>
                    <div class="w-11 h-11 rounded-xl bg-brand-600 text-white flex items-center justify-center mb-4">
                        <i class="fas {{ $step['icon'] }}"></i>
                    </div>
                    <h3 class="font-bold text-ink-900">{{ $step['title'] }}</h3>
                    <p class="mt-1.5 text-sm text-ink-500">{{ $step['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ==================== FEATURES ==================== --}}
<section class="py-16 lg:py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <h2 class="text-3xl font-extrabold text-ink-900">Built for reliable verification</h2>
            <p class="mt-3 text-ink-500">The details that make KamVerify dependable for everyday use.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @php
                $features = [
                    ['icon' => 'fa-bolt', 'title' => 'Automatic activation', 'text' => 'Numbers are assigned automatically once you confirm — no manual processing.'],
                    ['icon' => 'fa-rotate-left', 'title' => 'Automatic refunds', 'text' => 'If no SMS arrives before the timer ends, the order expires and your wallet is refunded automatically.'],
                    ['icon' => 'fa-tag', 'title' => 'Transparent pricing', 'text' => 'You always see the exact price before paying. No subscriptions, no hidden fees.'],
                    ['icon' => 'fa-shield-halved', 'title' => 'Privacy first', 'text' => 'Keep your personal number off third-party platforms. Numbers are single-use per activation.'],
                    ['icon' => 'fa-wallet', 'title' => 'Wallet billing', 'text' => 'Fund once, spend per activation. Full transaction history and refund tracking built in.'],
                    ['icon' => 'fa-headset', 'title' => 'Real support', 'text' => 'A built-in ticket system with a real conversation thread — not a dead contact form.'],
                ];
            @endphp
            @foreach($features as $feature)
                <div class="kv-card p-6">
                    <div class="w-11 h-11 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center mb-4">
                        <i class="fas {{ $feature['icon'] }} text-lg"></i>
                    </div>
                    <h3 class="font-bold text-ink-900">{{ $feature['title'] }}</h3>
                    <p class="mt-1.5 text-sm text-ink-500">{{ $feature['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ==================== TRUST / SAFETY ==================== --}}
<section class="py-16 lg:py-20 bg-ink-900 text-white relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-ink-900 via-ink-900 to-brand-900/40"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <h2 class="text-3xl font-extrabold">Your money is never at risk</h2>
                <p class="mt-4 text-ink-300 max-w-lg">
                    KamVerify only charges your wallet when a number is actually assigned. If anything goes wrong — no stock, provider failure, expired timer — the refund is automatic.
                </p>
                <div class="mt-8 grid sm:grid-cols-2 gap-4">
                    <div class="rounded-2xl bg-white/5 border border-white/10 p-5">
                        <i class="fas fa-lock text-brand-300 text-xl mb-3 block"></i>
                        <h3 class="font-bold">Secure payments</h3>
                        <p class="mt-1 text-sm text-ink-400">Deposits are verified server-side before your wallet is credited — never trusted from the browser.</p>
                    </div>
                    <div class="rounded-2xl bg-white/5 border border-white/10 p-5">
                        <i class="fas fa-clock-rotate-left text-brand-300 text-xl mb-3 block"></i>
                        <h3 class="font-bold">Auto-expiry refunds</h3>
                        <p class="mt-1 text-sm text-ink-400">Unused numbers expire on a visible countdown and the full amount returns to your wallet.</p>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl bg-white/5 border border-white/10 p-6 sm:p-8">
                <h3 class="font-bold text-lg mb-5">What you get with every order</h3>
                <ul class="space-y-4 text-sm">
                    <li class="flex gap-3"><i class="fas fa-check-circle text-brand-400 mt-0.5"></i><span><strong class="text-white">A dedicated number</strong><span class="text-ink-400"> — exclusive to your activation window.</span></span></li>
                    <li class="flex gap-3"><i class="fas fa-check-circle text-brand-400 mt-0.5"></i><span><strong class="text-white">Live SMS inbox</strong><span class="text-ink-400"> — messages stream into your order page in real time.</span></span></li>
                    <li class="flex gap-3"><i class="fas fa-check-circle text-brand-400 mt-0.5"></i><span><strong class="text-white">One-tap OTP copy</strong><span class="text-ink-400"> — codes are extracted automatically from each message.</span></span></li>
                    <li class="flex gap-3"><i class="fas fa-check-circle text-brand-400 mt-0.5"></i><span><strong class="text-white">Cancel anytime</strong><span class="text-ink-400"> — before the code arrives, cancel for a full refund.</span></span></li>
                    <li class="flex gap-3"><i class="fas fa-check-circle text-brand-400 mt-0.5"></i><span><strong class="text-white">Order history</strong><span class="text-ink-400"> — every activation, refund and timestamp on record.</span></span></li>
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ==================== PAYMENT METHODS ==================== --}}
<section class="py-14 lg:py-16 bg-ink-50 border-y border-ink-200/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-ink-900">Simple ways to pay</h2>
            <p class="mt-3 text-ink-500">Fund your KamVerify wallet with the payment method that works for you.</p>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 max-w-4xl mx-auto">
            <div class="kv-card p-5 text-center">
                <div class="w-12 h-12 rounded-xl bg-yellow-400 grid place-items-center mx-auto">
                    <span class="text-xs font-black text-ink-900 tracking-tight">MTN</span>
                </div>
                <p class="mt-3 text-sm font-bold text-ink-900">MTN Mobile Money</p>
                <p class="mt-0.5 text-xs text-emerald-600 font-semibold">Available now</p>
            </div>
            <div class="kv-card p-5 text-center">
                <div class="w-12 h-12 rounded-xl bg-orange-500 grid place-items-center mx-auto">
                    <i class="fas fa-mobile-screen text-white"></i>
                </div>
                <p class="mt-3 text-sm font-bold text-ink-900">Orange Money</p>
                <p class="mt-0.5 text-xs text-ink-400 font-semibold">Coming soon</p>
            </div>
            <div class="kv-card p-5 text-center">
                <div class="w-12 h-12 rounded-xl bg-amber-500 grid place-items-center mx-auto">
                    <i class="fab fa-bitcoin text-white text-lg"></i>
                </div>
                <p class="mt-3 text-sm font-bold text-ink-900">Bitcoin</p>
                <p class="mt-0.5 text-xs text-ink-400 font-semibold">Coming soon</p>
            </div>
            <div class="kv-card p-5 text-center">
                <div class="w-12 h-12 rounded-xl bg-ink-800 grid place-items-center mx-auto">
                    <i class="fas fa-credit-card text-white"></i>
                </div>
                <p class="mt-3 text-sm font-bold text-ink-900">Credit / Debit Cards</p>
                <p class="mt-0.5 text-xs text-ink-400 font-semibold">Coming soon</p>
            </div>
        </div>
        <p class="mt-6 text-center text-xs text-ink-400">
            All deposits are denominated in XAF and verified server-side before your wallet is credited.
        </p>
    </div>
</section>

{{-- ==================== FAQ ==================== --}}
<section id="faq" class="py-16 lg:py-20 bg-white">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-extrabold text-ink-900">Frequently asked questions</h2>
            <p class="mt-3 text-ink-500">Everything you need to know before your first activation.</p>
        </div>
        <div class="space-y-3" x-data="{ open: 0 }">
            @php
                $faqs = [
                    ['q' => 'What is KamVerify?', 'a' => 'KamVerify sells temporary virtual phone numbers that can receive SMS. Use one to receive a verification code without exposing your personal number.'],
                    ['q' => 'How much does a number cost?', 'a' => 'Pricing depends on the service and country you pick. You always see the exact price and your wallet balance before confirming — you\'re never charged more than shown.'],
                    ['q' => 'What if my SMS code never arrives?', 'a' => 'Every order has a visible countdown. If no code arrives before it expires, the order is cancelled automatically and the full amount is refunded to your wallet. You can also cancel manually before the code arrives.'],
                    ['q' => 'How do I pay?', 'a' => 'You fund your KamVerify wallet first, then spend from your balance per activation. Deposits are verified on our servers before funds appear — a payment is never marked successful just because the browser says so.'],
                    ['q' => 'Can I reuse a number?', 'a' => 'Numbers are tied to a single activation window. Once your order completes, cancels, or expires, the number is released. This keeps activations private and reliable.'],
                    ['q' => 'What can I use the numbers for?', 'a' => 'Legitimate verification and privacy purposes — receiving OTP codes for accounts you own. KamVerify is not a tool for bypassing platform security or abusing services.'],
                ];
            @endphp
            @foreach($faqs as $i => $faq)
                <div class="kv-card overflow-hidden">
                    <button @click="open = open === {{ $i }} ? -1 : {{ $i }}"
                            class="w-full flex items-center justify-between gap-4 px-5 py-4 text-left">
                        <span class="font-semibold text-ink-900 text-sm sm:text-base">{{ $faq['q'] }}</span>
                        <i class="fas fa-chevron-down text-ink-400 text-xs transition-transform" :class="{ 'rotate-180': open === {{ $i }} }"></i>
                    </button>
                    <div x-show="open === {{ $i }}" style="display:none">
                        <p class="px-5 pb-5 text-sm text-ink-500">{{ $faq['a'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ==================== FINAL CTA ==================== --}}
<section class="relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-r from-brand-700 to-cyan-800"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
        <h2 class="text-3xl font-extrabold text-white">Ready for your first number?</h2>
        <p class="mt-3 text-brand-100 max-w-xl mx-auto">Create a free account, fund your wallet, and get verified in minutes.</p>
        <div class="mt-8">
            @auth
                <a href="{{ route('orders.create') }}" class="kv-btn bg-white text-brand-700 hover:bg-brand-50 !px-8 !py-3.5 !text-base">
                    <i class="fas fa-bolt"></i> Buy a number
                </a>
            @else
                <a href="{{ route('register') }}" class="kv-btn bg-white text-brand-700 hover:bg-brand-50 !px-8 !py-3.5 !text-base">
                    Create free account
                </a>
            @endauth
        </div>
    </div>
</section>

<x-public-footer />

<script>
    function quickBuy() {
        return {
            serviceId: '',
            countryId: '',
            get url() {
                const base = @json(route('orders.create'));
                if (!this.serviceId && !this.countryId) return base;
                const params = new URLSearchParams();
                if (this.serviceId) params.set('service', this.serviceId);
                if (this.countryId) params.set('country', this.countryId);
                return base + '?' + params.toString();
            }
        };
    }
</script>
</body>
</html>
