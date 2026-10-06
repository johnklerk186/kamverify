import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Shared promo countdown + dismissal. endIso is the real promotion
// end_datetime rendered server-side; reaching zero hides the banner.
// Pricing is always enforced server-side — this is display only.
window.kvPromo = function (id, endIso) {
    return {
        expired: false,
        dismissed: false,
        cd: { d: '00', h: '00', m: '00', s: '00' },
        timer: null,
        init() {
            try { this.dismissed = localStorage.getItem('kv_promo_hide_' + id) === '1'; } catch (e) {}
            const end = new Date(endIso).getTime();
            const tick = () => {
                const left = Math.max(0, end - Date.now());
                if (left <= 0) { this.expired = true; clearInterval(this.timer); return; }
                const s = Math.floor(left / 1000);
                this.cd = {
                    d: String(Math.floor(s / 86400)).padStart(2, '0'),
                    h: String(Math.floor(s / 3600) % 24).padStart(2, '0'),
                    m: String(Math.floor(s / 60) % 60).padStart(2, '0'),
                    s: String(s % 60).padStart(2, '0'),
                };
            };
            tick();
            this.timer = setInterval(tick, 1000);
        },
        dismiss() {
            this.dismissed = true;
            try { localStorage.setItem('kv_promo_hide_' + id, '1'); } catch (e) {}
        },
    };
};

// ---- PWA install prompt ----
// Capture beforeinstallprompt early (it can fire before the dashboard
// component initializes) and remember real installs across sessions.
// The event is suppressed sitewide so the browser's own mini-infobar
// never competes with our dashboard prompt.
window.__kvBIP = null;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    window.__kvBIP = e;
});
window.addEventListener('appinstalled', () => {
    window.__kvBIP = null;
    try { localStorage.setItem('kv_pwa_installed', '1'); } catch (e) {}
});

// "Add KamVerify to your Home Screen" floating prompt — dashboard only.
// Shows ~2s after load, auto-dismisses at 30s, persists dismissal, and
// never renders when the app is already installed/standalone.
window.kvInstallPrompt = function () {
    return {
        visible: false,
        modal: null,          // 'ios' | 'android' | null
        busy: false,
        deferred: null,
        timers: [],
        _onBip: null,
        _onInstalled: null,

        isIOS() {
            const ua = navigator.userAgent || '';
            return /iphone|ipad|ipod/i.test(ua)
                || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        },
        isStandalone() {
            return window.matchMedia('(display-mode: standalone)').matches
                || window.matchMedia('(display-mode: fullscreen)').matches
                || navigator.standalone === true
                || document.referrer.startsWith('android-app://');
        },
        init() {
            if (this.isStandalone()) return;
            try {
                if (localStorage.getItem('kv_pwa_installed') === '1'
                    || localStorage.getItem('kv_a2hs_dismissed') === '1') return;
                // 24h snooze after an auto-dismiss — not permanent like the ×
                if (parseInt(localStorage.getItem('kv_a2hs_snooze') || '0', 10) > Date.now()) return;
                if (sessionStorage.getItem('kv_a2hs_shown') === '1') return;
                sessionStorage.setItem('kv_a2hs_shown', '1');
            } catch (e) {}

            if (window.__kvBIP) this.deferred = window.__kvBIP;
            this._onBip = (e) => { this.deferred = e; };
            this._onInstalled = () => {
                this.visible = false;
                this.modal = null;
                this._clearTimers();
                try { localStorage.setItem('kv_pwa_installed', '1'); } catch (e) {}
            };
            window.addEventListener('beforeinstallprompt', this._onBip);
            window.addEventListener('appinstalled', this._onInstalled);

            this.timers.push(setTimeout(() => this._show(), 2000));
        },
        _show() {
            if (this.isStandalone()) return this._destroy();
            this.visible = true;
            this.timers.push(setTimeout(() => this.snooze(), 30000));
        },
        async install() {
            if (this.isIOS()) { this.modal = 'ios'; return; }
            const p = this.deferred || window.__kvBIP;
            if (p && typeof p.prompt === 'function') {
                this.busy = true;
                try {
                    await p.prompt();
                    const { outcome } = await p.userChoice;
                    if (outcome === 'accepted') return this._destroy();
                } catch (e) { /* prompt rejected — fall through to manual steps */ }
                finally { this.busy = false; }
                this.deferred = null;
                window.__kvBIP = null;
            }
            this.modal = 'android';
        },
        dismiss() {
            // × button — user said no, don't ask again
            this.visible = false;
            this.modal = null;
            try { localStorage.setItem('kv_a2hs_dismissed', '1'); } catch (e) {}
            this._destroy();
        },
        snooze() {
            // 30s auto-dismiss — only snoozes, prompt can return next session/day
            this.visible = false;
            this.modal = null;
            try { localStorage.setItem('kv_a2hs_snooze', String(Date.now() + 86400000)); } catch (e) {}
            this._destroy();
        },
        closeModal() { this.modal = null; },
        _clearTimers() {
            this.timers.forEach(clearTimeout);
            this.timers = [];
        },
        _destroy() {
            this._clearTimers();
            if (this._onBip) window.removeEventListener('beforeinstallprompt', this._onBip);
            if (this._onInstalled) window.removeEventListener('appinstalled', this._onInstalled);
        },
        destroy() { this._destroy(); },
    };
};

Alpine.start();

// Scroll-reveal: elements opt in via [data-reveal] (+ optional
// data-reveal-delay="ms" for staggers). The hidden state is added
// here — only when an IntersectionObserver can un-hide it — so the
// page stays fully visible without JS, on old browsers, and for
// users who prefer reduced motion.
document.addEventListener('DOMContentLoaded', () => {
    const items = document.querySelectorAll('[data-reveal]');
    if (!items.length) return;
    if (!('IntersectionObserver' in window)) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const io = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            entry.target.classList.add('revealed');
            io.unobserve(entry.target);
        }
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    items.forEach((el) => {
        el.style.transitionDelay = `${parseInt(el.dataset.revealDelay || '0', 10)}ms`;
        el.classList.add('kv-reveal');
        io.observe(el);
    });
});
