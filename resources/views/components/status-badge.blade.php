@props(['status'])
<span {{ $attributes->merge(['class' => 'kv-badge ' . statusColor($status)]) }}>
    {{ ucfirst(str_replace('_', ' ', $status)) }}
</span>
