@props(['venue', 'locale', 'supportedLocales', 'labels', 'lobby', 'narration', 'scene', 'chapter', 'backdrop', 'menuItems', 'title' => null, 'welcome' => null, 'sidebar' => null, 'description' => null, 'ogImage' => null])
{{-- The fixed full-screen stage every chapter shares: photo, velvet curtains, chapter display, order-of-the-day panel and the concierge chat dock. --}}
@php
    $lobbyUrl = route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]);
    $currentKey = ['hall' => 'halls', 'service' => 'services'][$scene] ?? $scene;
    $currentLabel = collect($menuItems)->firstWhere('key', $currentKey)['label'] ?? $lobby['home'];
    $pageTitle = $title ?? $venue->name.' · AI Wedding Concierge';
    $pageDescription = \Illuminate\Support\Str::limit(strip_tags((string) ($description ?: $venue->translatedDescription($locale) ?: $venue->name)), 160);
    $pageImage = $ogImage ?: ($venue->cover_path ?: $backdrop['image']);
    $ogLocales = ['id' => 'id_ID', 'en' => 'en_US', 'ja' => 'ja_JP'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ url()->current() }}?lang={{ $locale }}">
    @foreach ($supportedLocales as $code)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ url()->current() }}?lang={{ $code }}">
    @endforeach
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $venue->name }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ url()->current() }}?lang={{ $locale }}">
    <meta property="og:image" content="{{ $pageImage }}">
    <meta property="og:locale" content="{{ $ogLocales[$locale] ?? 'id_ID' }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ $pageImage }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stage-page antialiased" style="--concierge-image: url('{{ $backdrop['avatarImage'] }}'); --stage-focus: {{ $backdrop['focus'] }}; --stage-focus-mobile: {{ $backdrop['focusMobile'] }}; --concierge-zoom: {{ $backdrop['avatarZoom'] }}; --concierge-focus: {{ $backdrop['avatarFocus'] }}">
    <main class="stage" data-lobby data-scene="{{ $scene }}" data-page="{{ $scene === 'lobby' ? 'home' : $scene }}" data-chapter="{{ $chapter }}" data-chat-dock="right">
        <div class="stage-loader" data-stage-loader role="status">
            <span class="curtain curtain-left" aria-hidden="true"></span>
            <span class="curtain curtain-right" aria-hidden="true"></span>
            <span class="stage-loader-chapter" aria-hidden="true">{{ $chapter }}</span>
            <p>{{ $lobby['loading'] }}</p>
        </div>
        <img src="{{ $backdrop['image'] }}" alt="" class="stage-image" fetchpriority="high">
        <div class="stage-shade"></div>
        @if ($scene !== 'hall')
            <img src="{{ $backdrop['character'] }}" alt="{{ $lobby['assistant'] }}" class="stage-character" data-pose="{{ $backdrop['pose'] }}">
        @endif
        <p class="stage-tour" data-stage-tour aria-live="polite"></p>

        <header class="stage-header">
            <a href="{{ $lobbyUrl }}" @if($scene !== 'lobby') data-stage-exit data-tour-line="{{ $narration['tour_lobby'] }}" @endif class="stage-brand">
                <x-venue-mark />
                <span class="min-w-0"><span class="stage-brand-name">{{ $venue->name }}</span><span class="stage-brand-city">{{ $venue->city }} · {{ $venue->country }}</span></span>
            </a>
            <div class="chapter-display" aria-live="polite">
                <span class="chapter-display-arrow" data-chapter-arrow aria-hidden="true">❦</span>
                <span class="chapter-display-code" data-chapter-code>{{ $chapter }}</span>
                <span class="chapter-display-label">{{ $currentLabel }}</span>
            </div>
            <div class="stage-tools">
                <x-sound-toggle :on="$lobby['sound_on']" :off="$lobby['sound_off']" />
                <nav aria-label="Language" class="stage-lang">
                    @foreach ($supportedLocales as $code)
                        <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                    @endforeach
                </nav>
            </div>
        </header>

        {{-- The order of the day: each section of the site is a chapter, welcome first, like a wedding programme card. --}}
        <nav class="journey-panel" aria-label="{{ $lobby['explore'] }}">
            <p class="journey-panel-title">{{ $lobby['explore'] }}</p>
            <ol class="journey-buttons">
                <li style="--strip-order: 0">
                    <a href="{{ $lobbyUrl }}" class="journey-button" @if($scene !== 'lobby') data-stage-exit data-tour-line="{{ $narration['tour_lobby'] }}" @else aria-current="page" @endif>
                        <span class="journey-key" aria-hidden="true">{{ \App\Http\Controllers\VenuePageController::CHAPTERS['lobby'] }}</span>
                        <span class="journey-label">{{ $lobby['home'] }}</span>
                    </a>
                </li>
                @foreach ($menuItems as $item)
                    <li style="--strip-order: {{ $loop->iteration }}">
                        <a href="{{ $item['href'] }}" class="journey-button"
                            data-stage-exit @if($item['tour']) data-tour-line="{{ $item['tour'] }}" @endif data-topic="{{ $item['topic'] }}"
                            @if($item['key'] === $currentKey) aria-current="page" @endif>
                            <span class="journey-key" aria-hidden="true">{{ $item['chapter'] }}</span>
                            <span class="journey-label">{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
            <span class="journey-panel-footer"><span class="status-dot" aria-hidden="true"></span>{{ $lobby['available'] }}</span>
        </nav>

        {{ $welcome }}

        {{ $sidebar }}

        {{ $slot }}

        <div data-chat-widget>
            @include('venue.chat')
        </div>
        <noscript><p class="stage-noscript">Aktifkan JavaScript untuk menggunakan navigasi dan AI Concierge. {{ $venue->phone }}</p></noscript>
    </main>
</body>
</html>
