@props(['name' => 'star'])
<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    @switch($name)
        @case('decor')
            <path d="M12 21v-7"/><path d="M12 14c-3 0-5-2.2-5-5 2.6 0 5 1.8 5 5Z"/><path d="M12 14c3 0 5-2.2 5-5-2.6 0-5 1.8-5 5Z"/><circle cx="12" cy="6" r="2.6"/>
            @break
        @case('catering')
            <path d="M7 3v8a2 2 0 0 0 2 2v8M5 3v5M9 3v5M16 21V3c2 1.5 3 4 3 7h-3"/>
            @break
        @case('photo')
            <path d="M4 8h3l1.5-2.5h7L17 8h3v11H4V8Z"/><circle cx="12" cy="13" r="3.4"/>
            @break
        @case('makeup')
            <path d="M9 21v-6h6v6M10 15V9l2-5 2 5v6"/><path d="M8 21h8"/>
            @break
        @case('music')
            <path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/>
            @break
        @case('planner')
            <rect x="5" y="4" width="14" height="17" rx="1.5"/><path d="M9 3v3M15 3v3M8.5 11l1.7 1.7 3.3-3.4M8.5 17h7"/>
            @break
        @case('room')
            <path d="M4 20V9l8-5 8 5v11M9 20v-6h6v6"/>
            @break
        @case('parking')
            <path d="M5 17h14M5 17v-5l2-5h10l2 5v5M5 12h14"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/>
            @break
        @case('transit')
            <rect x="5" y="3.5" width="14" height="13" rx="3"/><path d="M5 11h14M9 20l1.5-3.5M15 20l-1.5-3.5"/><circle cx="9" cy="14" r=".6"/><circle cx="15" cy="14" r=".6"/>
            @break
        @case('transport')
            <path d="M10.5 13.5 3 11l1-2 8 1 4-5a1.5 1.5 0 0 1 2 2l-5 4 1 8-2 1-2.5-7.5L6 15.5V18l-1.5 1-1-3-3-1 1-1.5h2.5Z"/>
            @break
        @case('payment')
            <rect x="3.5" y="6" width="17" height="12" rx="2"/><path d="M3.5 10h17M7 15h3"/>
            @break
        @case('security')
            <path d="M12 3.5 19 6v5.5c0 4.6-3 8.2-7 9.3-4-1.1-7-4.7-7-9.3V6l7-2.5Z"/><path d="m9.2 12 1.9 1.9 3.7-3.8"/>
            @break
        @case('place')
            <path d="M12 21s-6.5-5.4-6.5-11a6.5 6.5 0 0 1 13 0c0 5.6-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/>
            @break
        @case('wifi')
            <path d="M3 9.5a14 14 0 0 1 18 0M6 13a9.5 9.5 0 0 1 12 0M9 16.4a5 5 0 0 1 6 0"/><circle cx="12" cy="19.5" r=".8" fill="currentColor"/>
            @break
        @default
            <path d="m12 3.5 2.6 5.4 5.9.8-4.3 4.1 1 5.9L12 17l-5.2 2.7 1-5.9-4.3-4.1 5.9-.8L12 3.5Z"/>
    @endswitch
</svg>
