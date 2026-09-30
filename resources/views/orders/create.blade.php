<x-app-layout>
    <x-slot name="title">Buy a Number</x-slot>

    <script>
        // Buy-flow config lives in JS context (not the x-data attribute) so the
        // JSON slugs map cannot break the HTML attribute quoting.
        window.__kvBuyConfig = {
            quoteUrl: '{{ route('orders.quote') }}',
            countriesUrl: '{{ route('orders.countries') }}',
            balance: {{ (int) $wallet->balance }},
            initialService: {{ (int) request('service', 0) }},
            initialCountry: {{ (int) request('country', 0) }},
            slugs: @json($serviceSlugs)
        };
    </script>

    <div class="max-w-5xl mx-auto" x-data="buyFlow(window.__kvBuyConfig)">

        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-extrabold text-ink-900 tracking-tight">Buy a virtual number</h1>
            <p class="mt-1 text-sm text-ink-500">Choose a service and country — your wallet is charged only after a number is assigned.</p>
        </div>

        {{-- Stepper --}}
        <div class="flex items-center gap-2 sm:gap-3 mb-7">
            <template x-for="(label, i) in ['Select Service', 'Select Country', 'Get Number']" :key="i">
                <div class="flex items-center gap-2 sm:gap-3" :class="i > 0 ? 'flex-1' : ''">
                    <div x-show="i > 0" class="flex-1 h-px" :class="step > i ? 'bg-brand-400' : 'bg-ink-200'"></div>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full grid place-items-center text-xs font-bold transition-colors"
                             :class="step > i + 1 ? 'bg-brand-600 text-white' : (step === i + 1 ? 'bg-brand-600 text-white ring-4 ring-brand-100' : 'bg-ink-100 text-ink-400')">
                            <i x-show="step > i + 1" class="fas fa-check text-[10px]"></i>
                            <span x-show="step <= i + 1" x-text="i + 1"></span>
                        </div>
                        <span class="text-xs sm:text-sm font-semibold" :class="step >= i + 1 ? 'text-ink-900' : 'text-ink-400'" x-text="label"></span>
                    </div>
                </div>
            </template>
        </div>

        {{-- No-JS / degraded-mode fallback: removed automatically once Alpine initialises.
             -- Keeps the full buy flow working (and the service list visible) even if
             -- the Alpine component fails to start in the customer's browser. --}}
        <div x-data="{}" x-init="$el.remove()" class="kv-card mb-6">
            <div class="px-5 sm:px-6 py-4 border-b border-ink-100">
                <h2 class="font-bold text-ink-900">Buy a number</h2>
                <p class="text-xs text-ink-500 mt-0.5">Choose a service and country, then confirm.</p>
            </div>
            <form method="POST" action="{{ route('orders.store') }}" class="p-5 sm:p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-ink-600 mb-1.5">Service</label>
                    <select name="service_id" class="kv-input w-full" required>
                        <option value="">Choose a service…</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}" @selected((int) request('service') === $service->id)>{{ $service->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink-600 mb-1.5">Country</label>
                    <select name="country_id" class="kv-input w-full" required>
                        <option value="">Choose a country…</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" @selected((int) request('country') === $country->id)>{{ $country->name }} ({{ $country->dial_code }})</option>
                        @endforeach
                    </select>
                </div>
                @if(($services->firstWhere('slug', 'facebook')?->id ?? 0) === (int) request('service'))
                    <label class="flex items-start gap-2.5 text-xs text-ink-700">
                        <input type="checkbox" name="vpn_acknowledged" value="1" class="mt-0.5" required>
                        <span>I understand that I need a USA VPN connected on the same phone used for Facebook verification.</span>
                    </label>
                @endif
                <button type="submit" class="kv-btn-primary w-full"><i class="fas fa-bolt"></i> Purchase number</button>
            </form>
        </div>

        <div class="grid lg:grid-cols-3 gap-6 items-start">
            {{-- Main column --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- STEP 1: Service --}}
                <div class="kv-card" x-show="step === 1">
                    <div class="px-5 sm:px-6 py-4 border-b border-ink-100 flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-bold text-ink-900">Which service do you want to verify?</h2>
                        <div class="relative w-full sm:w-56">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-ink-400 text-xs"></i>
                            <input type="text" x-model="serviceQuery" placeholder="Search services…"
                                   class="kv-input !pl-8 !py-2 !text-base sm:!text-xs">
                        </div>
                    </div>
                    <div class="p-4 sm:p-5 grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-[26rem] overflow-y-auto">
                        @foreach($services as $service)
                            @php [$icon, $color] = serviceIcon($service->slug); @endphp
                            <a href="{{ route('orders.create', ['service' => $service->id]) }}"
                                    x-show="serviceMatch({{ $service->id }})"
                                    @click.prevent="selectService({{ $service->id }}, '{{ addslashes($service->name) }}')"
                                    class="flex items-center gap-3 rounded-xl border px-3.5 py-3 text-left transition"
                                    :class="serviceId === {{ $service->id }} ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500' : 'border-ink-200/70 hover:border-brand-300 hover:bg-ink-50'">
                                <i class="{{ $icon }} {{ $color }} text-lg w-5 text-center shrink-0"></i>
                                <span class="text-sm font-semibold text-ink-800 truncate min-w-0">{{ $service->name }}</span>
                            </a>
                        @endforeach
                    </div>
                    <div x-show="!anyServiceVisible()" x-cloak class="px-6 py-10 text-center text-sm text-ink-400">
                        No services match your search.
                    </div>
                </div>

                {{-- STEP 2: Country — service-specific list --}}
                <div class="kv-card" x-show="step === 2" x-cloak>
                    <div class="px-5 sm:px-6 pt-5 pb-4 sm:pt-6 border-b border-ink-100">
                        <h2 class="font-bold text-ink-900">2. Select Country</h2>
                        <p class="mt-0.5 text-xs text-ink-500">
                            Showing countries available for <strong class="text-ink-800" x-text="serviceName"></strong>
                        </p>

                        {{-- Large full-width search — 16px on mobile so no iOS zoom --}}
                        <div class="relative mt-4">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-ink-400 text-sm"></i>
                            <input type="text" x-model="countryQuery" placeholder="Search countries..."
                                   class="kv-input !pl-11 !py-3.5 !rounded-xl !text-base sm:!text-sm !bg-white">
                        </div>

                        {{-- Popular chips — wrap naturally on small screens --}}
                        <div x-show="!countriesLoading && popularCountries().length" x-cloak
                             class="mt-3 flex flex-wrap gap-2">
                            <template x-for="c in popularCountries()" :key="'pop-' + c.id">
                                <button type="button" @click="selectCountry(c.id, c.name)"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                        :class="countryId === c.id ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-ink-200/70 bg-white text-ink-700 hover:border-brand-300 hover:bg-ink-50'">
                                    <span x-text="c.flag"></span><span x-text="c.name"></span>
                                </button>
                            </template>
                        </div>

                        <p x-show="!countriesLoading && countryCount > 0" x-cloak
                           class="mt-3 text-xs font-medium text-ink-400">
                            <span x-text="countryCount"></span> countries available
                        </p>
                    </div>

                    {{-- Loading skeleton --}}
                    <div x-show="countriesLoading" class="p-4 sm:p-5 space-y-2.5">
                        <template x-for="i in 8" :key="i">
                            <div class="flex items-center gap-3.5 rounded-xl border border-ink-100 px-4 py-3.5">
                                <div class="w-7 h-5 rounded bg-ink-100 animate-pulse"></div>
                                <div class="h-3.5 w-32 rounded bg-ink-100 animate-pulse"></div>
                            </div>
                        </template>
                    </div>

                    {{-- Country cards --}}
                    <div x-show="!countriesLoading" class="p-4 sm:p-5 space-y-2.5 max-h-[28rem] overflow-y-auto">
                        <template x-for="c in filteredCountries()" :key="c.id">
                            <button type="button" @click="selectCountry(c.id, c.name)"
                                    class="w-full flex items-center gap-3 rounded-xl border px-4 py-3.5 text-left transition"
                                    :class="countryId === c.id ? 'border-brand-500 bg-brand-50' : 'border-ink-200/70 bg-white hover:border-brand-300 hover:bg-ink-50/60'">
                                <span class="text-xl leading-none shrink-0" x-text="c.flag"></span>
                                <span class="flex-1 min-w-0 text-sm font-semibold text-ink-800 truncate" x-text="c.name"></span>
                                <span x-show="c.popular" class="kv-badge bg-brand-50 text-brand-700 shrink-0">Popular</span>
                                <i class="fas fa-chevron-right text-ink-300 text-xs shrink-0"></i>
                            </button>
                        </template>
                    </div>
                    <div x-show="!countriesLoading && filteredCountries().length === 0" x-cloak class="px-6 py-10 text-center text-sm text-ink-400">
                        <span x-show="countryQuery">No countries match your search.</span>
                        <span x-show="!countryQuery">No countries available for this service right now.</span>
                    </div>
                </div>

                {{-- STEP 3: Quote + confirm --}}
                <div class="kv-card" x-show="step === 3" x-cloak>
                    <div class="px-5 sm:px-6 py-4 border-b border-ink-100">
                        <h2 class="font-bold text-ink-900">Confirm your purchase</h2>
                    </div>
                    <div class="p-5 sm:p-6">

                        {{-- Service-specific guidance --}}
                        <div x-show="serviceSlug() === 'whatsapp'" class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200/70 px-4 py-3 flex items-start gap-3">
                            <i class="fab fa-whatsapp text-emerald-600 mt-0.5"></i>
                            <p class="text-sm text-emerald-900">Enter the assigned number into WhatsApp's normal verification flow, then return here and wait for the SMS code to appear. KamVerify delivers the SMS — WhatsApp decides whether to accept the number.</p>
                        </div>
                        <div x-show="serviceSlug() === 'telegram'" class="mb-4 rounded-xl bg-sky-50 border border-sky-200/70 px-4 py-3 flex items-start gap-3">
                            <i class="fab fa-telegram text-sky-600 mt-0.5"></i>
                            <p class="text-sm text-sky-900">Use the assigned number in Telegram's normal sign-up or login verification, then wait for the SMS code here. KamVerify delivers the SMS — Telegram decides whether to accept the number.</p>
                        </div>
                        <div x-show="serviceSlug() === 'tiktok'" class="mb-4 rounded-xl bg-ink-50 border border-ink-200/70 px-4 py-3 flex items-start gap-3">
                            <i class="fab fa-tiktok text-ink-800 mt-0.5"></i>
                            <p class="text-sm text-ink-800">Use the assigned number in TikTok's normal verification process, then wait for the SMS code here. KamVerify delivers the SMS — TikTok decides whether to accept the number.</p>
                        </div>

                        {{-- IMPORTANT: Facebook / Meta VPN notice — must be acknowledged before purchase --}}
                        <div x-show="serviceSlug() === 'facebook' && !vpnAck" x-cloak
                             class="mb-4 rounded-xl border-2 border-amber-400 bg-amber-50 px-4 py-4 sm:px-5">
                            <div class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-xl bg-amber-400/30 grid place-items-center shrink-0">
                                    <i class="fas fa-triangle-exclamation text-amber-600"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-extrabold text-amber-900 text-sm uppercase tracking-wide">Important — Facebook Verification</p>
                                    <p class="mt-1.5 text-sm text-amber-900 leading-relaxed">
                                        When using KamVerify for Facebook verification, you must have a <strong>VPN connected to a USA location</strong> on the same phone you are using to request the Facebook verification code.
                                    </p>
                                    <p class="mt-1.5 text-sm text-amber-900 leading-relaxed">
                                        Make sure the VPN is already connected before you start the Facebook verification process and keep it connected while requesting the code. If you request the Facebook code without the required VPN connection, you may not receive the verification code.
                                    </p>
                                    <label class="mt-3 flex items-start gap-2.5 cursor-pointer">
                                        <input type="checkbox" x-model="vpnAgreed" @change="vpnError = false"
                                               class="mt-0.5 w-4 h-4 rounded border-amber-400 text-brand-600 focus:ring-brand-500">
                                        <span class="text-sm font-semibold text-amber-900">I understand that I need to use a USA VPN on the same phone I am using to request my Facebook verification code.</span>
                                    </label>
                                    <p x-show="vpnError" x-cloak class="mt-2 text-xs font-semibold text-red-600">
                                        Please confirm that you understand the Facebook VPN requirement before continuing.
                                    </p>
                                    <button type="button" @click="acknowledgeVpn()"
                                            class="mt-3 inline-flex items-center gap-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold px-4 py-2.5 transition-colors">
                                        <i class="fas fa-check"></i> I Understand — Continue
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Loading --}}
                        <div x-show="quoteLoading" class="space-y-3">
                            <div class="h-4 w-40 rounded bg-ink-100 animate-pulse"></div>
                            <div class="h-9 w-56 rounded bg-ink-100 animate-pulse"></div>
                            <div class="h-4 w-64 rounded bg-ink-100 animate-pulse"></div>
                        </div>

                        {{-- Quote result --}}
                        <div x-show="!quoteLoading && quote && quote.available" x-cloak>
                            <div class="flex items-center justify-between rounded-xl bg-emerald-50 border border-emerald-200/70 px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <i class="fas fa-circle-check text-emerald-600"></i>
                                    <span class="text-sm font-semibold text-emerald-800">Numbers available</span>
                                </div>
                                <span class="text-2xl font-extrabold text-ink-900" x-text="quote?.price_formatted"></span>
                            </div>
                            <ul class="mt-4 space-y-2 text-sm text-ink-600">
                                <li class="flex justify-between"><span>Service</span><strong class="text-ink-900" x-text="serviceName"></strong></li>
                                <li class="flex justify-between"><span>Country</span><strong class="text-ink-900" x-text="countryName"></strong></li>
                                <li class="flex justify-between"><span>Activation window</span><strong class="text-ink-900">15 minutes</strong></li>
                                <li class="flex justify-between"><span>Your balance</span>
                                    <strong :class="quote?.sufficient ? 'text-emerald-600' : 'text-red-600'" x-text="quote?.balance_formatted"></strong>
                                </li>
                            </ul>
                            <div x-show="!quote?.sufficient" x-cloak class="mt-4 rounded-xl bg-amber-50 border border-amber-200/70 px-4 py-3 flex items-start gap-2.5">
                                <i class="fas fa-triangle-exclamation text-amber-600 mt-0.5"></i>
                                <p class="text-sm text-amber-800">
                                    Insufficient balance.
                                    <a href="{{ route('wallet.deposit') }}" class="font-semibold underline">Deposit funds</a> to continue.
                                </p>
                            </div>
                            <p class="mt-4 text-xs text-ink-400 flex items-start gap-2">
                                <i class="fas fa-shield-halved mt-0.5 text-brand-500"></i>
                                Your wallet is charged only after a number is assigned. If no SMS arrives, the order can be cancelled or expires for a full refund.
                            </p>
                        </div>

                        {{-- Unavailable / error --}}
                        <div x-show="!quoteLoading && quote && !quote.available" x-cloak class="rounded-xl bg-red-50 border border-red-200/70 px-4 py-4 flex items-start gap-3">
                            <i class="fas fa-circle-xmark text-red-500 mt-0.5"></i>
                            <div>
                                <p class="text-sm font-semibold text-red-800">Not available</p>
                                <p class="mt-0.5 text-sm text-red-700" x-text="quote?.message"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar: selection summary --}}
            <div class="kv-card p-5 lg:sticky lg:top-6">
                <h3 class="font-bold text-ink-900 text-sm">Your selection</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-ink-500 shrink-0">Service</dt>
                        <dd class="font-semibold text-ink-900 text-right min-w-0 truncate" x-text="serviceName || '—'"></dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-ink-500 shrink-0">Country</dt>
                        <dd class="font-semibold text-ink-900 text-right min-w-0 truncate" x-text="countryName || '—'"></dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 pt-3 border-t border-ink-100">
                        <dt class="text-ink-500">Price</dt>
                        <dd class="font-extrabold text-ink-900 text-lg" x-text="quote?.available ? quote.price_formatted : '—'"></dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-ink-500">Balance</dt>
                        <dd class="font-semibold text-ink-700">{{ xaf($wallet->balance) }}</dd>
                    </div>
                </dl>

                <div class="mt-5 space-y-2.5">
                    <template x-if="step === 2">
                        <button type="button" @click="step = 1" class="kv-btn-ghost w-full !justify-start"><i class="fas fa-arrow-left"></i> Change service</button>
                    </template>
                    <template x-if="step === 3">
                        <button type="button" @click="step = 2" class="kv-btn-ghost w-full !justify-start"><i class="fas fa-arrow-left"></i> Change country</button>
                    </template>

                    <button type="button" x-show="step === 1" @click="serviceId && gateServiceNotice()" :disabled="!serviceId"
                            class="kv-btn-primary w-full">
                        Continue <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="button" x-show="step === 2" @click="fetchQuote()" :disabled="!countryId || quoteLoading"
                            class="kv-btn-primary w-full" x-cloak>
                        <span x-show="!quoteLoading">Check availability <i class="fas fa-arrow-right"></i></span>
                        <span x-show="quoteLoading"><i class="fas fa-circle-notch fa-spin"></i> Checking…</span>
                    </button>

                    <form method="POST" action="{{ route('orders.store') }}" x-show="step === 3" x-cloak
                          x-ref="orderForm" @submit="onSubmit($event)">
                        @csrf
                        <input type="hidden" name="service_id" :value="serviceId">
                        <input type="hidden" name="country_id" :value="countryId">
                        <input type="hidden" name="vpn_acknowledged" :value="vpnAck ? '1' : ''">
                        <button type="submit" :disabled="!quote?.available || !quote?.sufficient || purchasing || (serviceSlug() === 'facebook' && !vpnAck)"
                                class="kv-btn-primary w-full h-11">
                            <span x-show="!purchasing && !(serviceSlug() === 'facebook' && !vpnAck)"><i class="fas fa-bolt"></i> Purchase number</span>
                            <span x-show="serviceSlug() === 'facebook' && !vpnAck"><i class="fas fa-lock"></i> Confirm VPN notice above</span>
                            <span x-show="purchasing"><i class="fas fa-circle-notch fa-spin"></i> Purchasing…</span>
                        </button>
                        <p x-show="vpnError" x-cloak class="mt-2 text-xs font-semibold text-red-600 text-center">
                            Please confirm that you understand the Facebook VPN requirement before continuing.
                        </p>
                    </form>
                </div>

                <p class="mt-4 text-[11px] leading-relaxed text-ink-400 text-center">
                    Charged only on successful assignment.<br>Auto-refund on expiry or failure.
                </p>
            </div>
        </div>

        {{-- Service instruction modal — shown once before country selection,
             dismissible per service. Config lives in serviceNotices above. --}}
        <div x-show="noticeSlug" x-cloak x-transition.opacity.duration.200ms
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink-950/60 backdrop-blur-sm"
             role="dialog" aria-modal="true">
            <div x-show="noticeSlug"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 class="w-full max-w-md max-h-[85vh] flex flex-col rounded-2xl bg-white shadow-2xl border border-ink-100 overflow-hidden">
                <div class="px-5 sm:px-6 py-5 overflow-y-auto">
                    <div class="flex items-center gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 grid place-items-center shrink-0">
                            <i class="fas fa-triangle-exclamation text-amber-600"></i>
                        </div>
                        <h3 class="font-bold text-ink-900 text-base leading-snug min-w-0"
                            x-text="serviceNotices[noticeSlug] ? serviceNotices[noticeSlug].title : ''"></h3>
                    </div>
                    <p class="mt-4 text-sm text-ink-700 leading-relaxed">
                        <strong class="font-extrabold text-ink-900">IMPORTANT:</strong>
                        <span x-text="serviceNotices[noticeSlug] ? serviceNotices[noticeSlug].message : ''"></span>
                    </p>
                    <label class="mt-4 flex items-center gap-2.5 cursor-pointer select-none">
                        <input type="checkbox" x-model="noticeDontShow"
                               class="w-4 h-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                        <span class="text-sm font-medium text-ink-700">Don't show again for this service</span>
                    </label>
                </div>
                <div class="px-5 sm:px-6 py-4 border-t border-ink-100 bg-ink-50/60 shrink-0">
                    <button type="button" @click="confirmNotice()" class="kv-btn-primary w-full h-11">
                        OK, I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function buyFlow(config) {
            return {
                step: 1,
                serviceId: config.initialService || null,
                serviceName: null,
                countryId: config.initialCountry || null,
                countryName: null,
                serviceQuery: '',
                countryQuery: '',
                quote: null,
                quoteLoading: false,
                purchasing: false,
                vpnAgreed: false,
                vpnAck: false,
                vpnError: false,

                // Service instruction notices — shown on service pick,
                // before country selection. Add WhatsApp/TikTok entries
                // here when ready; each is remembered per-service via
                // localStorage key kv_notice_off_<slug>.
                serviceNotices: {
                    facebook: {
                        title: 'Instructions for Facebook / Meta Viewpoints',
                        message: 'Connect a VPN to the SAME COUNTRY as this number BEFORE opening Facebook, and keep it on while you request the code. Failure to do so you won\u2019t receive a code.',
                    },
                    telegram: {
                        title: 'Instructions for Telegram',
                        message: 'Connect a VPN to the SAME COUNTRY as this number BEFORE opening Telegram, and keep it on while you request the code. Failure to do so you won\u2019t receive a code.',
                    },
                },
                noticeSlug: null,
                noticeDontShow: false,

                // Per-service country list (fetched after service pick)
                serviceCountries: [],
                popularIds: [],
                countryCount: 0,
                countriesLoading: false,

                serviceNames: @json($services->pluck('name', 'id')),
                countryNames: @json($countries->pluck('name', 'id')),

                init() {
                    if (this.serviceId) this.serviceName = this.serviceNames[this.serviceId];
                    if (this.countryId) this.countryName = this.countryNames[this.countryId];
                    if (this.serviceId && this.countryId) this.fetchQuote();
                    else if (this.serviceId) this.gateServiceNotice();
                },
                serviceMatch(id) {
                    return !this.serviceQuery || this.serviceNames[id].toLowerCase().includes(this.serviceQuery.toLowerCase());
                },
                anyServiceVisible() {
                    return Object.values(this.serviceNames).some(n => !this.serviceQuery || n.toLowerCase().includes(this.serviceQuery.toLowerCase()));
                },
                filteredCountries() {
                    const q = this.countryQuery.trim().toLowerCase();
                    if (!q) return this.serviceCountries;
                    return this.serviceCountries.filter(c =>
                        c.name.toLowerCase().includes(q) ||
                        c.code.toLowerCase().includes(q) ||
                        (c.dial_code || '').includes(q)
                    );
                },
                popularCountries() {
                    return this.popularIds
                        .map(id => this.serviceCountries.find(c => c.id === id))
                        .filter(Boolean);
                },
                async loadCountries() {
                    this.countriesLoading = true;
                    this.serviceCountries = [];
                    this.popularIds = [];
                    this.countryCount = 0;
                    this.countryQuery = '';
                    try {
                        const res = await fetch(config.countriesUrl + '?service_id=' + this.serviceId, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const data = await res.json();
                        this.serviceCountries = data.countries || [];
                        this.popularIds = data.popular || [];
                        this.countryCount = data.count || 0;
                    } catch (e) {
                        this.serviceCountries = [];
                        this.countryCount = 0;
                    }
                    this.countriesLoading = false;
                },
                serviceSlug() {
                    return (config.slugs && config.slugs[this.serviceId]) || '';
                },
                acknowledgeVpn() {
                    if (!this.vpnAgreed) { this.vpnError = true; return; }
                    this.vpnAck = true;
                    this.vpnError = false;
                },
                onSubmit(event) {
                    if (this.serviceSlug() === 'facebook' && !this.vpnAck) {
                        event.preventDefault();
                        this.vpnError = true;
                        return;
                    }
                    this.purchasing = true;
                },
                selectService(id, name) {
                    this.serviceId = id; this.serviceName = name; this.quote = null;
                    this.countryId = null; this.countryName = null;
                    this.vpnAck = false; this.vpnAgreed = false; this.vpnError = false;
                    this.gateServiceNotice();
                },
                // Show the service's instruction modal first if one is
                // configured and not permanently dismissed; otherwise go
                // straight to country selection.
                gateServiceNotice() {
                    const slug = this.serviceSlug();
                    if (this.serviceNotices[slug] && !this.noticeDismissed(slug)) {
                        this.noticeSlug = slug;
                        this.noticeDontShow = false;
                        return;
                    }
                    this.proceedToCountries();
                },
                noticeDismissed(slug) {
                    try { return localStorage.getItem('kv_notice_off_' + slug) === '1'; }
                    catch (e) { return false; }
                },
                confirmNotice() {
                    if (this.noticeSlug && this.noticeDontShow) {
                        try { localStorage.setItem('kv_notice_off_' + this.noticeSlug, '1'); } catch (e) {}
                    }
                    this.noticeSlug = null;
                    this.proceedToCountries();
                },
                proceedToCountries() {
                    this.step = 2;
                    this.loadCountries();
                },
                selectCountry(id, name) {
                    this.countryId = id; this.countryName = name; this.quote = null;
                    this.fetchQuote(); // straight to price + Get Number
                },
                async fetchQuote() {
                    this.step = 3;
                    this.quoteLoading = true;
                    this.quote = null;
                    try {
                        const res = await fetch(config.quoteUrl + '?country_id=' + this.countryId + '&service_id=' + this.serviceId, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        this.quote = await res.json();
                    } catch (e) {
                        this.quote = { available: false, message: 'Unable to check availability. Please try again.' };
                    }
                    this.quoteLoading = false;
                }
            }
        }
    </script>
</x-app-layout>
