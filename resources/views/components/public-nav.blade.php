<nav x-data="{ open: false }" class="bg-white/95 backdrop-blur border-b border-ink-200/70 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <a href="{{ url('/') }}"><x-kv-logo size="w-9 h-9" /></a>

            <div class="hidden md:flex items-center gap-1">
                <a href="{{ url('/#services') }}" class="px-3 py-2 text-sm font-medium text-ink-600 hover:text-brand-600 transition-colors">Services</a>
                <a href="{{ url('/#countries') }}" class="px-3 py-2 text-sm font-medium text-ink-600 hover:text-brand-600 transition-colors">Countries</a>
                <a href="{{ url('/#how-it-works') }}" class="px-3 py-2 text-sm font-medium text-ink-600 hover:text-brand-600 transition-colors">How it works</a>
                <a href="{{ url('/#faq') }}" class="px-3 py-2 text-sm font-medium text-ink-600 hover:text-brand-600 transition-colors">FAQ</a>
                <a href="{{ route('blog.index') }}" class="px-3 py-2 text-sm font-medium text-ink-600 hover:text-brand-600 transition-colors {{ request()->routeIs('blog.*') ? 'text-brand-600' : '' }}">Blog</a>
            </div>

            <div class="hidden md:flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="kv-btn-primary"><i class="fas fa-gauge-high"></i> Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="kv-btn-ghost">Log in</a>
                    <a href="{{ route('register') }}" class="kv-btn-primary">Get started</a>
                @endauth
            </div>

            <button @click="open = !open" class="md:hidden p-2 text-ink-600" aria-label="Menu">
                <i class="fas" :class="open ? 'fa-times' : 'fa-bars'"></i>
            </button>
        </div>
    </div>

    <div x-show="open" x-collapse class="md:hidden border-t border-ink-200/70 bg-white" style="display:none">
        <div class="px-4 py-3 space-y-1">
            <a href="{{ url('/#services') }}" @click="open=false" class="block px-3 py-2.5 rounded-lg text-sm font-medium text-ink-700 hover:bg-ink-50">Services</a>
            <a href="{{ url('/#countries') }}" @click="open=false" class="block px-3 py-2.5 rounded-lg text-sm font-medium text-ink-700 hover:bg-ink-50">Countries</a>
            <a href="{{ url('/#how-it-works') }}" @click="open=false" class="block px-3 py-2.5 rounded-lg text-sm font-medium text-ink-700 hover:bg-ink-50">How it works</a>
            <a href="{{ url('/#faq') }}" @click="open=false" class="block px-3 py-2.5 rounded-lg text-sm font-medium text-ink-700 hover:bg-ink-50">FAQ</a>
            <a href="{{ route('blog.index') }}" @click="open=false" class="block px-3 py-2.5 rounded-lg text-sm font-medium text-ink-700 hover:bg-ink-50">Blog</a>
        </div>
        <div class="px-4 pb-4 flex gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="kv-btn-primary flex-1">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="kv-btn-secondary flex-1">Log in</a>
                <a href="{{ route('register') }}" class="kv-btn-primary flex-1">Get started</a>
            @endauth
        </div>
    </div>
</nav>
