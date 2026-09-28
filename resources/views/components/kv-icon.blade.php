@props(['size' => 'w-9 h-9'])
<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 ' . $size]) }}>
    <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full" role="img" aria-label="KamVerify">
        <defs>
            <linearGradient id="kv-tile" x1="0" y1="0" x2="48" y2="48" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#0d9488"/>
                <stop offset="1" stop-color="#0e7490"/>
            </linearGradient>
        </defs>
        <rect width="48" height="48" rx="12" fill="url(#kv-tile)"/>
        {{-- K stem --}}
        <path d="M13 12.5 V35.5" stroke="#ffffff" stroke-width="4" stroke-linecap="round"/>
        {{-- K upper arm --}}
        <path d="M24.5 12.5 L13.8 24.6" stroke="#ffffff" stroke-width="4" stroke-linecap="round"/>
        {{-- K lower leg extended into a check mark --}}
        <path d="M13.8 24.6 L20.5 34.5 L34 14.5" stroke="#ffffff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
    </svg>
</span>
