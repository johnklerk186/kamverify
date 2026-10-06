{{-- "Add KamVerify to your Home Screen" — customer dashboard only.
     Timing/visibility logic lives in kvInstallPrompt() (resources/js/app.js):
     2s delay, 30s max, localStorage dismissal, standalone suppression,
     native beforeinstallprompt on Android, manual steps elsewhere. --}}
<div x-data="kvInstallPrompt()" x-cloak>

    {{-- Floating prompt — above the mobile bottom nav (h-16), clear of
         the Smartsupp bubble (bottom-right) on desktop. --}}
    <div x-show="visible"
         class="kv-a2hs fixed z-40 bottom-20 inset-x-4 sm:inset-x-auto sm:left-6 sm:bottom-6 sm:w-[24rem]">
        <div class="relative flex items-start gap-3 rounded-2xl border border-ink-200/80 bg-white p-4 shadow-pop">
            <x-kv-icon size="w-11 h-11" class="rounded-xl shadow-card mt-0.5" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-ink-900">Install KamVerify</p>
                <p class="mt-0.5 text-[13px] leading-snug text-ink-500">Add KamVerify to your home screen for quick access anytime, just like an app.</p>
                <button type="button" @click="install()" :disabled="busy"
                        class="mt-2.5 inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 py-2 text-[13px] font-semibold text-white transition-colors hover:bg-brand-700 disabled:opacity-60">
                    <i class="fas fa-arrow-down-to-line text-[11px]"></i>
                    <span x-text="busy ? 'Opening…' : 'Add to Home Screen'">Add to Home Screen</span>
                </button>
            </div>
            <button type="button" @click="dismiss()" aria-label="Dismiss"
                    class="absolute -top-2 -right-2 flex h-7 w-7 items-center justify-center rounded-full border border-ink-200 bg-white text-ink-400 shadow-card transition-colors hover:text-ink-700">
                <i class="fas fa-xmark text-xs"></i>
            </button>
        </div>
    </div>

    {{-- Manual install instructions (iOS / browsers without beforeinstallprompt) --}}
    <div x-show="modal" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" @keydown.escape.window="closeModal()">
        <div class="fixed inset-0 bg-ink-950/40" @click="closeModal()"></div>
        <div class="relative w-full max-w-sm rounded-2xl bg-white p-5 shadow-pop">
            <div class="flex items-start gap-3">
                <x-kv-icon size="w-10 h-10" class="rounded-xl shadow-card" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-ink-900">Add KamVerify to your home screen</p>
                    <p class="text-xs text-ink-400 mt-0.5">Takes about 10 seconds</p>
                </div>
                <button type="button" @click="closeModal()" aria-label="Close"
                        class="text-ink-400 transition-colors hover:text-ink-700">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>

            {{-- iPhone / iPad (Safari) --}}
            <ol x-show="modal === 'ios'" class="mt-4 space-y-3 text-[13px] text-ink-600">
                <li class="flex items-start gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[11px] font-bold text-brand-600">1</span>
                    <span>Tap the <span class="inline-flex items-center gap-1 rounded-md border border-ink-200 bg-ink-50 px-1.5 py-0.5 text-ink-700"><i class="fas fa-arrow-up-from-bracket"></i> Share</span> icon in Safari's toolbar.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[11px] font-bold text-brand-600">2</span>
                    <span>Scroll down and tap <span class="font-semibold text-ink-800">Add to Home Screen</span> <i class="fas fa-plus-square text-ink-400"></i>.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[11px] font-bold text-brand-600">3</span>
                    <span>Tap <span class="font-semibold text-ink-800">Add</span> in the top-right corner.</span>
                </li>
            </ol>

            {{-- Android / desktop fallback (no beforeinstallprompt) --}}
            <ol x-show="modal === 'android'" class="mt-4 space-y-3 text-[13px] text-ink-600">
                <li class="flex items-start gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[11px] font-bold text-brand-600">1</span>
                    <span>Open your browser's menu <i class="fas fa-ellipsis-vertical text-ink-400"></i> (top-right in Chrome).</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[11px] font-bold text-brand-600">2</span>
                    <span>Tap <span class="font-semibold text-ink-800">Install app</span> or <span class="font-semibold text-ink-800">Add to Home screen</span>.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[11px] font-bold text-brand-600">3</span>
                    <span>Confirm — KamVerify will appear on your home screen.</span>
                </li>
            </ol>

            <button type="button" @click="closeModal()"
                    class="mt-5 w-full rounded-lg border border-ink-200 py-2 text-[13px] font-semibold text-ink-600 transition-colors hover:bg-ink-50">
                Got it
            </button>
        </div>
    </div>
</div>
