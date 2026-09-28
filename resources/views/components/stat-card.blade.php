@props(['label', 'value', 'icon' => 'fa-circle', 'accent' => 'brand', 'href' => null, 'sub' => null])
@php
    $accents = [
        'brand' => 'bg-brand-50 text-brand-600',
        'green' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'red'   => 'bg-red-50 text-red-600',
        'blue'  => 'bg-sky-50 text-sky-600',
        'violet'=> 'bg-violet-50 text-violet-600',
        'ink'   => 'bg-ink-100 text-ink-600',
    ];
    $accentClass = $accents[$accent] ?? $accents['brand'];
@endphp
<{{ $href ? 'a' : 'div' }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'kv-card p-5 flex items-center gap-4' . ($href ? ' hover:border-brand-300 transition-colors' : '')]) }}>
    <div class="w-11 h-11 rounded-xl {{ $accentClass }} flex items-center justify-center shrink-0">
        <i class="fas {{ $icon }} text-lg"></i>
    </div>
    <div class="min-w-0">
        <p class="text-xs font-medium text-ink-500 uppercase tracking-wide">{{ $label }}</p>
        <p class="text-xl font-bold text-ink-900 truncate">{{ $value }}</p>
        @if($sub)
            <p class="text-xs text-ink-400 truncate">{{ $sub }}</p>
        @endif
    </div>
</{{ $href ? 'a' : 'div' }}>
