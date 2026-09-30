import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

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
