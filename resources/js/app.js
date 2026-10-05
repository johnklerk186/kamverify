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
