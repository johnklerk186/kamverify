<x-public-layout>
    <x-slot name="title">Insights &amp; Guides</x-slot>

    <div class="bg-ink-50 border-b border-ink-200/70">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-center">
            <p class="text-xs font-bold uppercase tracking-widest text-brand-600">Insights &amp; Guides</p>
            <h1 class="mt-2 text-3xl font-extrabold text-ink-900">Blog</h1>
            <p class="mt-2 text-sm text-ink-500 max-w-xl mx-auto">
                Tips, guides, and news about virtual numbers, SMS verification, and online privacy.
            </p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @if($posts->isEmpty())
            <div class="kv-card py-16 text-center">
                <i class="fas fa-newspaper text-3xl text-ink-300"></i>
                <p class="mt-3 text-sm text-ink-500">No articles published yet — check back soon.</p>
            </div>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($posts as $post)
                    <a href="{{ route('blog.show', $post) }}" class="kv-card p-6 flex flex-col hover:shadow-md hover:border-brand-300 transition group">
                        <div class="w-11 h-11 rounded-xl bg-brand-50 text-brand-600 grid place-items-center">
                            <i class="fas {{ $post->icon }}"></i>
                        </div>
                        <p class="mt-4 text-[11px] font-semibold uppercase tracking-wider text-ink-400">
                            {{ $post->published_at->format('M d, Y') }} · {{ $post->readingTime() }} min read
                        </p>
                        <h2 class="mt-1.5 font-bold text-ink-900 leading-snug group-hover:text-brand-700 transition-colors">
                            {{ $post->title }}
                        </h2>
                        <p class="mt-2 text-sm text-ink-500 leading-relaxed flex-1">{{ $post->excerpt }}</p>
                        <span class="mt-4 text-xs font-semibold text-brand-600">
                            Read more <i class="fas fa-arrow-right text-[10px]"></i>
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="mt-10">
                {{ $posts->links() }}
            </div>
        @endif
    </div>
</x-public-layout>
