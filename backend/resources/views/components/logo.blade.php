@props(['tam' => 40])
{{-- Runa Dagaz (ᛞ) — imagotipo de la plataforma, tomado de los mockups de servilleta. --}}
<svg width="{{ $tam }}" height="{{ $tam }}" viewBox="0 0 100 100" aria-hidden="true" focusable="false" {{ $attributes }}>
    <polygon points="20,10 50,50 20,90" fill="none" stroke="currentColor" stroke-width="7" stroke-linejoin="round" />
    <polygon points="80,10 50,50 80,90" fill="none" stroke="currentColor" stroke-width="7" stroke-linejoin="round" />
    <line x1="20" y1="10" x2="80" y2="10" stroke="currentColor" stroke-width="7" stroke-linecap="round" />
    <line x1="20" y1="90" x2="80" y2="90" stroke="currentColor" stroke-width="7" stroke-linecap="round" />
</svg>
