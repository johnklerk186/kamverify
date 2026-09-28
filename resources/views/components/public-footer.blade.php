<footer class="bg-ink-900 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid gap-10 md:grid-cols-4">
            <div>
                <x-kv-logo size="w-9 h-9" :light="true" />
                <p class="mt-4 text-sm text-ink-400 max-w-xs">
                    Virtual phone numbers for receiving SMS verification codes online — fast, private, and pay-per-use.
                </p>
            </div>
            <div>
                <h4 class="text-sm font-semibold uppercase tracking-wider text-ink-400 mb-4">Product</h4>
                <ul class="space-y-2.5 text-sm text-ink-300">
                    <li><a href="{{ url('/#services') }}" class="hover:text-white transition-colors">Services</a></li>
                    <li><a href="{{ url('/#countries') }}" class="hover:text-white transition-colors">Countries</a></li>
                    <li><a href="{{ url('/#how-it-works') }}" class="hover:text-white transition-colors">How it works</a></li>
                    <li><a href="{{ route('blog.index') }}" class="hover:text-white transition-colors">Blog</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-white transition-colors">Create account</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-semibold uppercase tracking-wider text-ink-400 mb-4">Legal</h4>
                <ul class="space-y-2.5 text-sm text-ink-300">
                    <li><a href="{{ route('pages.terms') }}" class="hover:text-white transition-colors">Terms of Service</a></li>
                    <li><a href="{{ route('pages.privacy') }}" class="hover:text-white transition-colors">Privacy Policy</a></li>
                    <li><a href="{{ route('pages.refund') }}" class="hover:text-white transition-colors">Refund Policy</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-semibold uppercase tracking-wider text-ink-400 mb-4">Support</h4>
                <ul class="space-y-2.5 text-sm text-ink-300">
                    <li><a href="{{ route('pages.contact') }}" class="hover:text-white transition-colors">Contact us</a></li>
                    <li><a href="{{ url('/#faq') }}" class="hover:text-white transition-colors">FAQ</a></li>
                    <li><a href="{{ route('support.index') }}" class="hover:text-white transition-colors">Support center</a></li>
                </ul>
            </div>
        </div>
        <div class="mt-10 pt-6 border-t border-white/10">
            <p class="text-[11px] uppercase tracking-wider text-ink-500 mb-3">Payment methods</p>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/5 border border-white/10 px-2.5 py-1.5 text-xs font-semibold text-ink-300">
                    <span class="w-5 h-5 rounded bg-yellow-400 grid place-items-center text-[8px] font-black text-ink-900">MTN</span>
                    MTN Mobile Money
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/5 border border-white/10 px-2.5 py-1.5 text-xs font-semibold text-ink-300">
                    <i class="fas fa-mobile-screen text-orange-400"></i> Orange Money
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/5 border border-white/10 px-2.5 py-1.5 text-xs font-semibold text-ink-300">
                    <i class="fab fa-bitcoin text-amber-400"></i> Bitcoin
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-lg bg-white/5 border border-white/10 px-2.5 py-1.5 text-xs font-semibold text-ink-300">
                    <i class="fas fa-credit-card text-ink-300"></i> Visa · Mastercard
                </span>
            </div>
        </div>
        <div class="mt-6 pt-6 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-ink-500">
            <p>&copy; {{ date('Y') }} KamVerify. All rights reserved.</p>
            <p>Virtual numbers for legitimate verification and privacy use.</p>
        </div>
    </div>
</footer>
