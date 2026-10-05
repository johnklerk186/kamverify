<x-admin-layout>
    <x-slot name="title">Order {{ $order->order_id }}</x-slot>
    <x-slot name="header">Order detail</x-slot>

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.orders.index') }}" class="w-9 h-9 rounded-xl border border-ink-200 bg-white grid place-items-center text-ink-500 hover:text-ink-800 transition">
                    <i class="fas fa-arrow-left text-sm"></i>
                </a>
                <div>
                    <h1 class="text-xl font-extrabold text-ink-900 font-mono tracking-tight">{{ $order->order_id }}</h1>
                    <p class="text-xs text-ink-500">{{ $order->created_at->format('M d, Y H:i:s') }}</p>
                </div>
            </div>
            <x-status-badge :status="$order->status" />
        </div>

        <div class="grid lg:grid-cols-3 gap-5 items-start">
            {{-- Main --}}
            <div class="lg:col-span-2 space-y-5">
                {{-- Number --}}
                <div class="kv-card p-6">
                    <p class="text-xs font-semibold uppercase tracking-wider text-ink-400">Assigned number</p>
                    <p class="mt-1.5 text-2xl font-extrabold font-mono text-ink-900">{{ $order->phone_number ?? 'Not assigned' }}</p>
                    <div class="mt-3 flex items-center gap-3 text-sm text-ink-600">
                        @php [$icon, $color] = serviceIcon($order->service->slug ?? ''); @endphp
                        <i class="{{ $icon }} {{ $color }}"></i> {{ $order->service->name ?? '—' }}
                        <span class="text-ink-300">·</span>
                        {{ countryFlag($order->country->code ?? null) }} {{ $order->country->name ?? '—' }}
                    </div>
                </div>

                {{-- SMS --}}
                <div class="kv-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-ink-100">
                        <h2 class="font-bold text-ink-900 text-sm">SMS messages ({{ $order->smsMessages->count() }})</h2>
                    </div>
                    @if($order->smsMessages->isEmpty())
                        <div class="py-8 text-center text-sm text-ink-400">No SMS received on this order.</div>
                    @else
                        <ul class="divide-y divide-ink-100">
                            @foreach($order->smsMessages as $sms)
                                <li class="px-5 py-4">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-semibold text-ink-600">From: {{ $sms->sender }}</span>
                                        <span class="text-ink-400">{{ $sms->received_at->format('M d, H:i:s') }}</span>
                                    </div>
                                    <p class="mt-1.5 text-sm text-ink-800 whitespace-pre-wrap break-words">{{ $sms->message }}</p>
                                    @if($sms->otp_code)
                                        <span class="mt-2 inline-block kv-badge bg-emerald-100 text-emerald-700 font-mono">OTP: {{ $sms->otp_code }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Provider response --}}
                @if($order->provider_response)
                    <div class="kv-card p-5">
                        <h2 class="font-bold text-ink-900 text-sm mb-3">Provider response</h2>
                        <pre class="text-xs bg-ink-900 text-ink-100 rounded-xl p-4 overflow-x-auto font-mono">{{ json_encode($order->provider_response, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="space-y-5">
                <div class="kv-card p-5">
                    <h3 class="font-bold text-ink-900 text-sm">Customer</h3>
                    <a href="{{ route('admin.users.show', $order->user) }}" class="mt-3 flex items-center gap-3 rounded-xl border border-ink-200/70 p-3 hover:border-brand-300 transition">
                        <span class="w-9 h-9 rounded-full bg-brand-100 text-brand-700 grid place-items-center text-xs font-bold">
                            {{ strtoupper(substr($order->user->name ?? 'U', 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink-900 truncate">{{ $order->user->name ?? '—' }}</p>
                            <p class="text-xs text-ink-400 truncate">{{ $order->user->email ?? '—' }}</p>
                        </div>
                    </a>
                </div>

                <div class="kv-card p-5">
                    <h3 class="font-bold text-ink-900 text-sm">Financials</h3>
                    <dl class="mt-3 space-y-2.5 text-sm">
                        <div class="flex justify-between"><dt class="text-ink-500">Customer paid</dt><dd class="font-bold text-ink-900">{{ xaf($order->selling_price) }}</dd></div>
                        @if($order->promotion_id)
                            <div class="flex justify-between">
                                <dt class="text-ink-500">Promotion</dt>
                                <dd class="text-brand-700 font-medium">{{ $order->promotion->name ?? '#' . $order->promotion_id }} · −{{ xaf($order->discount_amount) }} off {{ xaf($order->normal_price) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between"><dt class="text-ink-500">Provider cost</dt><dd class="text-ink-900">{{ number_format($order->purchase_price, 2) }} USD</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-500">Profit</dt><dd class="font-semibold text-emerald-600">{{ xaf($order->profit) }}</dd></div>
                        @if($order->refund_amount)
                            <div class="flex justify-between pt-2.5 border-t border-ink-100">
                                <dt class="text-emerald-600">Refunded</dt><dd class="font-bold text-emerald-600">{{ xaf($order->refund_amount) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Audited admin override --}}
                @if(!in_array($order->status, ['completed', 'refunded']))
                    <div class="kv-card p-5 border-amber-200/70 bg-amber-50/40" x-data="{ open: false }">
                        <button type="button" @click="open = !open" class="w-full flex items-center justify-between text-left">
                            <h3 class="font-bold text-ink-900 text-sm flex items-center gap-2">
                                <i class="fas fa-triangle-exclamation text-amber-600"></i> Admin override
                            </h3>
                            <i class="fas fa-chevron-down text-xs text-ink-400 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <form x-show="open" x-cloak method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="mt-4 space-y-3">
                            @csrf @method('PUT')
                            <div>
                                <label class="kv-label !mb-1 text-xs">Force status</label>
                                <select name="status" class="kv-input !py-2" required>
                                    <option value="">Choose…</option>
                                    <option value="failed">Failed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="refunded">Refunded (credits wallet)</option>
                                </select>
                            </div>
                            <div>
                                <label class="kv-label !mb-1 text-xs">Reason (required, audit-logged)</label>
                                <textarea name="reason" rows="2" required minlength="5" maxlength="500" class="kv-input !py-2" placeholder="Why is this override necessary?"></textarea>
                            </div>
                            <p class="text-[11px] text-amber-700">This bypasses the normal lifecycle and is recorded in the audit log with your identity.</p>
                            <button type="submit" class="kv-btn-danger w-full !py-2 text-xs"><i class="fas fa-gavel"></i> Apply override</button>
                        </form>
                    </div>
                @endif

                <div class="kv-card p-5">
                    <h3 class="font-bold text-ink-900 text-sm">Details</h3>
                    <dl class="mt-3 space-y-2.5 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-ink-500">Provider</dt><dd class="font-medium text-ink-900 text-right">{{ $order->provider->name ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-ink-500">Activation ID</dt><dd class="font-mono text-xs text-ink-900 text-right break-all">{{ $order->provider_activation_id ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-500">Created</dt><dd class="text-ink-900">{{ $order->created_at->format('M d, H:i') }}</dd></div>
                        @if($order->expires_at)
                            <div class="flex justify-between"><dt class="text-ink-500">Expired at</dt><dd class="text-ink-900">{{ $order->expires_at->format('M d, H:i') }}</dd></div>
                        @endif
                        @if($order->completed_at)
                            <div class="flex justify-between"><dt class="text-ink-500">Completed</dt><dd class="text-ink-900">{{ $order->completed_at->format('M d, H:i') }}</dd></div>
                        @endif
                        @if($order->cancelled_at)
                            <div class="flex justify-between"><dt class="text-ink-500">Cancelled</dt><dd class="text-ink-900">{{ $order->cancelled_at->format('M d, H:i') }}</dd></div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
