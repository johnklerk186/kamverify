<x-app-layout>
    <x-slot name="title">Order {{ $order->order_id }}</x-slot>

    <div class="max-w-5xl mx-auto"
         x-data="orderLive({
            feedUrl: '{{ route('orders.sms-feed', $order) }}',
            expiresAt: {{ $order->expires_at ? "'".$order->expires_at->toIso8601String()."'" : 'null' }},
            active: {{ $order->isActive() ? 'true' : 'false' }},
            knownCount: {{ $smsMessages->count() }},
            knownSigs: @json($smsMessages->map(fn($s) => $s->received_at->format('M d, Y H:i:s').'|'.$s->sender.'|'.$s->message)->values())
         })"
         x-init="init()">

        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div class="flex items-center gap-3">
                <a href="{{ route('orders.index') }}" class="w-9 h-9 rounded-xl border border-ink-200 bg-white grid place-items-center text-ink-500 hover:text-ink-800 hover:border-ink-300 transition">
                    <i class="fas fa-arrow-left text-sm"></i>
                </a>
                <div>
                    <h1 class="text-xl font-extrabold text-ink-900 font-mono tracking-tight">{{ $order->order_id }}</h1>
                    <p class="text-xs text-ink-500">Placed {{ $order->created_at->format('M d, Y \a\t H:i') }}</p>
                </div>
            </div>
            <x-status-badge :status="$order->status" x-bind:class="liveStatus && liveStatus !== '{{ $order->status }}' ? 'hidden' : ''" />
            <span x-show="liveStatus && liveStatus !== '{{ $order->status }}'" x-cloak class="kv-badge" :class="statusClass(liveStatus)" x-text="statusLabel(liveStatus)"></span>
        </div>

        <div class="grid lg:grid-cols-5 gap-6 items-start">
            {{-- Left: number + sms --}}
            <div class="lg:col-span-3 space-y-5">

                {{-- Number card --}}
                <div class="kv-card overflow-hidden">
                    <div class="px-5 sm:px-6 py-4 border-b border-ink-100 flex items-center justify-between">
                        <h2 class="font-bold text-ink-900 text-sm">Your number</h2>
                        <div class="flex items-center gap-2 text-xs text-ink-500">
                            <span>{{ countryFlag($order->country->code) }}</span>
                            <span>{{ $order->country->name }}</span>
                        </div>
                    </div>
                    <div class="px-5 sm:px-6 py-6">
                        @if($order->phone_number)
                            <div class="flex items-center gap-3">
                                <p class="text-2xl sm:text-3xl font-extrabold font-mono text-ink-900 tracking-tight break-all">{{ $order->phone_number }}</p>
                                <button type="button" onclick="kvCopy('{{ $order->phone_number }}', this)"
                                        class="w-9 h-9 rounded-xl bg-ink-100 text-ink-500 hover:bg-brand-50 hover:text-brand-600 transition grid place-items-center shrink-0" title="Copy number">
                                    <i class="fas fa-copy text-sm"></i>
                                </button>
                            </div>
                        @else
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-52 rounded-lg bg-ink-100 animate-pulse"></div>
                                <p class="text-sm text-ink-400">Assigning number…</p>
                            </div>
                        @endif

                        <div class="mt-4 flex items-center gap-2">
                            @php [$icon, $color] = serviceIcon($order->service->slug); @endphp
                            <i class="{{ $icon }} {{ $color }}"></i>
                            <span class="text-sm font-medium text-ink-700">{{ $order->service->name }}</span>
                        </div>

                        {{-- Countdown --}}
                        <div x-show="isLive && countdown" x-cloak class="mt-5 rounded-xl bg-amber-50 border border-amber-200/70 px-4 py-3 flex items-center gap-3">
                            <i class="fas fa-clock text-amber-600"></i>
                            <div class="flex-1">
                                <p class="text-xs font-medium text-amber-700 uppercase tracking-wide">Time remaining</p>
                                <p class="text-lg font-extrabold font-mono text-amber-800" x-text="countdown"></p>
                            </div>
                            <div class="w-24 h-1.5 rounded-full bg-amber-200 overflow-hidden hidden sm:block">
                                <div class="h-full bg-amber-500 transition-all duration-1000" :style="'width:' + countdownPct + '%'"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SMS inbox --}}
                <div class="kv-card">
                    <div class="px-5 sm:px-6 py-4 border-b border-ink-100 flex items-center justify-between">
                        <h2 class="font-bold text-ink-900 text-sm">SMS inbox</h2>
                        <div class="flex items-center gap-3">
                            <span x-show="isLive" x-cloak class="flex items-center gap-1.5 text-xs text-emerald-600 font-medium">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                </span>
                                Live
                            </span>
                            <button type="button" @click="poll(true)" class="text-xs font-semibold text-brand-600 hover:text-brand-700" :disabled="polling">
                                <i class="fas fa-rotate" :class="polling ? 'fa-spin' : ''"></i> Refresh
                            </button>
                        </div>
                    </div>

                    {{-- Waiting state --}}
                    <div x-show="isLive && (knownCount + newMessages.length) === 0" x-cloak class="px-6 py-12 text-center">
                        <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-50 grid place-items-center mb-4">
                            <i class="fas fa-satellite-dish text-2xl text-amber-500"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-ink-900">Waiting for SMS</h3>
                        <p class="mt-1 text-sm text-ink-500 max-w-sm mx-auto">Use the number above to request your code. This page updates automatically.</p>
                        <div class="mt-5 flex justify-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-brand-400 animate-bounce" style="animation-delay:0ms"></span>
                            <span class="w-2 h-2 rounded-full bg-brand-400 animate-bounce" style="animation-delay:150ms"></span>
                            <span class="w-2 h-2 rounded-full bg-brand-400 animate-bounce" style="animation-delay:300ms"></span>
                        </div>
                    </div>

                    {{-- No messages, order finished --}}
                    @if($smsMessages->isEmpty() && !$order->isActive())
                        <x-empty-state icon="fa-message-slash" title="No SMS received" message="No messages arrived before this order ended." />
                    @endif

                    {{-- Messages --}}
                    <ul class="divide-y divide-ink-100" x-show="(knownCount + newMessages.length) > 0">
                        @foreach($smsMessages as $sms)
                            <li class="px-5 sm:px-6 py-4">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 grid place-items-center shrink-0">
                                        <i class="fas fa-message text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="text-xs font-semibold text-ink-500">From: {{ $sms->sender }}</p>
                                            <p class="text-xs text-ink-400">{{ $sms->received_at->format('H:i:s') }}</p>
                                        </div>
                                        <p class="mt-1 text-sm text-ink-800 break-words whitespace-pre-wrap">{{ $sms->message }}</p>
                                        @if($sms->otp_code)
                                            <button type="button" onclick="kvCopy('{{ $sms->otp_code }}', this)"
                                                    class="mt-3 inline-flex items-center gap-2 rounded-xl bg-emerald-600 text-white px-4 py-2 text-sm font-bold font-mono hover:bg-emerald-700 transition">
                                                <i class="fas fa-copy"></i> {{ $sms->otp_code }}
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                        <template x-for="msg in newMessages" :key="msg.received_at + msg.sender">
                            <li class="px-5 sm:px-6 py-4 bg-emerald-50/40">
                                <div class="flex items-start gap-3.5">
                                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 grid place-items-center shrink-0">
                                        <i class="fas fa-message text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="text-xs font-semibold text-ink-500" x-text="'From: ' + msg.sender"></p>
                                            <p class="text-xs text-ink-400" x-text="msg.received_at"></p>
                                        </div>
                                        <p class="mt-1 text-sm text-ink-800 break-words whitespace-pre-wrap" x-text="msg.message"></p>
                                        <button x-show="msg.otp_code" type="button" @click="kvCopy(msg.otp_code, $el)"
                                                class="mt-3 inline-flex items-center gap-2 rounded-xl bg-emerald-600 text-white px-4 py-2 text-sm font-bold font-mono hover:bg-emerald-700 transition">
                                            <i class="fas fa-copy"></i> <span x-text="msg.otp_code"></span>
                                        </button>
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>

            {{-- Right: details + actions --}}
            <div class="lg:col-span-2 space-y-5">
                <div class="kv-card p-5">
                    <h3 class="font-bold text-ink-900 text-sm">Order details</h3>
                    <dl class="mt-4 space-y-2.5 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-ink-500 shrink-0">Service</dt><dd class="font-medium text-ink-900 text-right min-w-0 truncate">{{ $order->service->name }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-ink-500 shrink-0">Country</dt><dd class="font-medium text-ink-900 text-right min-w-0 truncate">{{ $order->country->name }}</dd></div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-500 shrink-0">Price</dt>
                            <dd class="font-bold text-ink-900 text-right">
                                @if($order->promotion_id && $order->normal_price)
                                    <span class="text-xs font-medium text-ink-400 line-through mr-1.5">{{ xaf($order->normal_price) }}</span>{{ xaf($order->selling_price) }}
                                    <span class="kv-badge bg-brand-50 text-brand-700 !text-[10px] ml-1">PROMO</span>
                                @else
                                    {{ xaf($order->selling_price) }}
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3"><dt class="text-ink-500 shrink-0">SMS received</dt><dd class="font-medium text-ink-900 text-right" x-text="knownCount + newMessages.length">{{ $smsMessages->count() }}</dd></div>
                        @if($order->expires_at)
                            <div class="flex justify-between"><dt class="text-ink-500">Expires</dt><dd class="font-medium text-ink-900">{{ $order->expires_at->format('H:i') }}</dd></div>
                        @endif
                        @if($order->refund_amount)
                            <div class="flex justify-between pt-2.5 border-t border-ink-100">
                                <dt class="text-emerald-600 font-medium">Refunded</dt>
                                <dd class="font-bold text-emerald-600">{{ xaf($order->refund_amount) }}</dd>
                            </div>
                        @endif
                        <div x-show="refunded && !{{ $order->refund_amount ? 'true' : 'false' }}" x-cloak class="flex justify-between pt-2.5 border-t border-ink-100">
                            <dt class="text-emerald-600 font-medium">Refunded</dt>
                            <dd class="font-bold text-emerald-600">{{ xaf($order->selling_price) }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Completed banner --}}
                <div x-show="liveStatus === 'completed' || '{{ $order->status }}' === 'completed'" x-cloak
                     class="rounded-xl bg-emerald-50 border border-emerald-200/70 px-4 py-4 flex items-start gap-3">
                    <i class="fas fa-circle-check text-emerald-600 mt-0.5 text-lg"></i>
                    <div>
                        <p class="text-sm font-bold text-emerald-800">Order completed</p>
                        <p class="mt-0.5 text-xs text-emerald-700">Your verification SMS arrived and this order closed automatically.</p>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="space-y-2.5">
                    @if($order->isCancelling())
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 flex items-start gap-3">
                            <i class="fas fa-circle-notch fa-spin text-amber-600 mt-0.5"></i>
                            <div class="text-sm text-amber-800">
                                <strong>Cancellation in progress.</strong> Your refund will be processed automatically within a few minutes unless a code arrives first.
                            </div>
                        </div>
                    @elseif($order->canCancel())
                        {{-- Hidden live as soon as the SMS lands / order resolves --}}
                        <div x-data="{ confirming: false }"
                             x-show="!liveStatus || ['number_assigned','waiting_for_sms'].includes(liveStatus)">
                            <button type="button" x-show="!confirming" @click="confirming = true" class="kv-btn-danger w-full h-11">
                                <i class="fas fa-xmark"></i> Cancel &amp; refund
                            </button>
                            <form x-show="confirming" x-cloak method="POST" action="{{ route('orders.cancel', $order) }}"
                                  class="rounded-xl border border-red-200 bg-red-50 p-4 space-y-3">
                                @csrf
                                @method('DELETE')
                                <p class="text-sm font-medium text-red-800">Cancel this order and refund {{ xaf($order->selling_price) }} to your wallet?</p>
                                <div class="flex gap-2">
                                    <button type="submit" class="kv-btn-danger flex-1 !py-2 text-xs">Yes, cancel</button>
                                    <button type="button" @click="confirming = false" class="kv-btn-secondary flex-1 !py-2 text-xs">Keep order</button>
                                </div>
                            </form>
                        </div>
                    @endif

                    @if($order->isActive() && !$order->canCancel())
                        <p class="text-xs text-ink-400 text-center px-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Cancellation is no longer available — the SMS has been received.
                        </p>
                    @elseif(!$order->isActive() && !$order->canCancel() && !$order->refund_amount && in_array($order->status, ['completed']))
                        <p class="text-xs text-ink-400 text-center px-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Completed orders can't be cancelled.
                        </p>
                    @endif

                    @if(in_array($order->status, ['refunded', 'expired']) || $order->refund_amount)
                        <div class="rounded-xl bg-emerald-50 border border-emerald-200/70 px-4 py-3 flex items-start gap-2.5">
                            <i class="fas fa-circle-check text-emerald-600 mt-0.5"></i>
                            <p class="text-sm text-emerald-800">
                                {{ $order->refund_amount ? xaf($order->refund_amount).' was refunded to your wallet.' : 'This order was refunded to your wallet.' }}
                            </p>
                        </div>
                    @endif

                    <a href="{{ route('orders.create') }}" class="kv-btn-secondary w-full">
                        <i class="fas fa-plus"></i> Buy another number
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function orderLive(config) {
            return {
                isLive: config.active,
                liveStatus: null,
                messages: [],
                knownCount: config.knownCount,
                knownSigs: config.knownSigs || [],
                newMessages: [],
                countdown: null,
                countdownPct: 100,
                polling: false,
                refunded: false,
                timer: null,
                countdownTimer: null,
                totalSeconds: null,

                statusClass(s) {
                    const map = {
                        pending: 'bg-ink-100 text-ink-700', processing: 'bg-sky-100 text-sky-700',
                        number_assigned: 'bg-brand-100 text-brand-700', waiting_for_sms: 'bg-amber-100 text-amber-700',
                        sms_received: 'bg-emerald-100 text-emerald-700', completed: 'bg-emerald-100 text-emerald-700',
                        cancelled: 'bg-red-100 text-red-700', refunded: 'bg-orange-100 text-orange-700',
                        expired: 'bg-ink-100 text-ink-600', failed: 'bg-red-100 text-red-700'
                    };
                    return map[s] || 'bg-ink-100 text-ink-700';
                },
                statusLabel(s) { return s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()); },

                init() {
                    if (config.expiresAt) this.startCountdown(new Date(config.expiresAt));
                    if (this.isLive) {
                        this.poll();
                        this.timer = setInterval(() => this.poll(), 8000);
                    }
                },
                startCountdown(expiry) {
                    const tick = () => {
                        const diff = expiry - new Date();
                        if (diff <= 0) { this.countdown = '00:00'; this.countdownPct = 0; clearInterval(this.countdownTimer); return; }
                        const m = Math.floor(diff / 60000), s = Math.floor((diff % 60000) / 1000);
                        this.countdown = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                        if (this.totalSeconds === null) this.totalSeconds = diff / 1000;
                        this.countdownPct = Math.max(0, Math.min(100, (diff / 1000 / this.totalSeconds) * 100));
                    };
                    tick();
                    this.countdownTimer = setInterval(tick, 1000);
                },
                async poll(manual = false) {
                    if (this.polling) return;
                    this.polling = true;
                    try {
                        const res = await fetch(config.feedUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        if (!res.ok) throw new Error();
                        const data = await res.json();
                        this.liveStatus = data.status;
                        this.refunded = ['refunded', 'expired', 'cancelled'].includes(data.status);
                        // Server-known messages
                        data.messages.forEach(m => {
                            const sig = m.received_at + '|' + m.sender + '|' + m.message;
                            if (!this.knownSigs.includes(sig) && !this.newMessages.some(n => n.received_at + '|' + n.sender + '|' + n.message === sig)) {
                                this.newMessages.push(m);
                            }
                        });
                        if (!data.is_active && this.timer) { clearInterval(this.timer); this.timer = null; this.isLive = false; }
                        if (data.status === 'sms_received' && this.timer) { clearInterval(this.timer); this.timer = setInterval(() => this.poll(), 20000); }
                    } catch (e) { /* keep existing state */ }
                    this.polling = false;
                }
            }
        }
    </script>
</x-app-layout>
