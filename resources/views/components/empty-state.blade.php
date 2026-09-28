@props(['icon' => 'fa-inbox', 'title' => 'Nothing here yet', 'message' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-12 px-6 text-center']) }}>
    <div class="w-14 h-14 rounded-2xl bg-ink-100 flex items-center justify-center mb-4">
        <i class="fas {{ $icon }} text-2xl text-ink-400"></i>
    </div>
    <h3 class="text-sm font-semibold text-ink-900">{{ $title }}</h3>
    @if($message)
        <p class="mt-1 text-sm text-ink-500 max-w-sm">{{ $message }}</p>
    @endif
    @if(!$slot->isEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
