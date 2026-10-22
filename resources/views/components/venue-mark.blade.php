{{-- The brand mark: two interlocking rings, drawn rather than lettered so it reads at any size. --}}
<span {{ $attributes->merge(['class' => 'venue-mark']) }} aria-hidden="true">
    <svg viewBox="0 0 32 32" width="24" height="24" fill="none">
        <circle cx="12" cy="19" r="7" stroke="currentColor" stroke-width="1.8"/>
        <circle cx="20" cy="19" r="7" stroke="var(--gold, #e3c27c)" stroke-width="1.8"/>
        <path d="M16 12.2 13.4 7.6h5.2L16 12.2Z" fill="var(--gold, #e3c27c)" stroke="var(--gold, #e3c27c)" stroke-width="1" stroke-linejoin="round"/>
    </svg>
</span>
