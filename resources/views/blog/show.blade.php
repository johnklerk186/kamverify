<x-public-layout>
    <x-slot name="title">{{ $post->title }}</x-slot>

    <div class="bg-ink-50 border-b border-ink-200/70">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <a href="{{ route('blog.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700">
                <i class="fas fa-arrow-left text-[10px]"></i> Insights &amp; Guides
            </a>
            <h1 class="mt-3 text-2xl sm:text-3xl font-extrabold text-ink-900 leading-tight">{{ $post->title }}</h1>
            <p class="mt-3 text-xs font-semibold uppercase tracking-wider text-ink-400">
                {{ $post->published_at->format('M d, Y') }} · {{ $post->readingTime() }} min read
            </p>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <article class="kv-prose text-sm sm:text-base text-ink-700 leading-relaxed">
            {!! $post->body !!}
        </article>

        <div class="mt-10 kv-card p-6 text-center">
            <h2 class="font-bold text-ink-900">Ready to try it?</h2>
            <p class="mt-1 text-sm text-ink-500">Get a virtual number and receive your code in seconds.</p>
            <a href="{{ route('register') }}" class="kv-btn-primary mt-4 inline-flex">
                <i class="fas fa-bolt"></i> Create free account
            </a>
        </div>

        @if($related->isNotEmpty())
            <h2 class="mt-12 font-bold text-ink-900 text-lg">More guides</h2>
            <div class="mt-4 grid sm:grid-cols-3 gap-4">
                @foreach($related as $item)
                    <a href="{{ route('blog.show', $item) }}" class="kv-card p-4 hover:border-brand-300 transition">
                        <p class="text-[11px] font-semibold text-ink-400">{{ $item->published_at->format('M d, Y') }}</p>
                        <p class="mt-1 text-sm font-semibold text-ink-800 leading-snug">{{ $item->title }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-public-layout>
