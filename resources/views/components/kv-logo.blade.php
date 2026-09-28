@props(['size' => 'w-9 h-9', 'light' => false])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5 select-none']) }}>
    <x-kv-icon :size="$size" />
    <span class="text-xl font-extrabold tracking-tight leading-none {{ $light ? 'text-white' : 'text-ink-900' }}">
        Kam<span class="text-brand-500">Verify</span>
    </span>
</span>
