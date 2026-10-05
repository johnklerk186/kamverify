@props(['autoOpen' => false, 'names' => []])

{{--
    Temporary-service-outage notice.
    Opens automatically after login (autoOpen from a session flash) or
    on demand — any element can re-open it with:
        window.dispatchEvent(new CustomEvent('kv-service-outage', { detail: 'Facebook' }))
    so clicking a flagged service always re-shows the notice even after
    the login copy was dismissed.
--}}
<div x-data="{ open: {{ $autoOpen ? 'true' : 'false' }}, name: '{{ implode(' & ', array_map('addslashes', $names)) }}' }"
     x-on:kv-service-outage.window="if ($event.detail) name = $event.detail; open = true"
     x-on:keydown.escape.window="open = false">
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms
         class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-ink-950/60 backdrop-blur-sm"
         role="dialog" aria-modal="true" @click.self="open = false">
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-ink-100 overflow-hidden">
            <div class="px-5 sm:px-6 py-5">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 grid place-items-center shrink-0">
                        <i class="fas fa-triangle-exclamation text-amber-600"></i>
                    </div>
                    <h3 class="font-bold text-ink-900 text-base leading-snug">Temporarily unavailable</h3>
                </div>
                <p class="mt-4 text-sm text-ink-700 leading-relaxed">
                    <span x-text="name"></span> verification is temporarily unavailable while we resolve a
                    technical issue. We're working to restore the service as soon as possible.
                    Thank you for your patience.
                </p>
            </div>
            <div class="px-5 sm:px-6 py-4 border-t border-ink-100 bg-ink-50/60">
                <button type="button" @click="open = false" class="kv-btn-primary w-full h-11">Got it</button>
            </div>
        </div>
    </div>
</div>
