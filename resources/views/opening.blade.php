<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wedding Venue AI</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stage-page antialiased">
    <main class="stage stage-opening" data-chapter="I">
        <div class="stage-loader" data-stage-loader role="status">
            <span class="curtain curtain-left" aria-hidden="true"></span>
            <span class="curtain curtain-right" aria-hidden="true"></span>
            <span class="stage-loader-chapter" aria-hidden="true">I</span>
            <p>{{ $opening['loading'] }}</p>
        </div>
        <img src="{{ $openingImage }}" alt="" class="stage-image" fetchpriority="high">
        <div class="stage-shade"></div>

        <header class="stage-header">
            <span class="stage-brand">
                <x-venue-mark />
                <span class="stage-brand-name">Wedding Venue AI</span>
            </span>
            <div class="stage-tools">
                <x-sound-toggle :on="$opening['sound_on']" :off="$opening['sound_off']" />
                <nav aria-label="Language" class="stage-lang">
                    @foreach ($supportedLocales as $code)
                        <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                    @endforeach
                </nav>
            </div>
        </header>

        <section class="opening-panel" aria-labelledby="opening-title">
            <p class="lobby-eyebrow">{{ $opening['eyebrow'] }}</p>
            <h1 id="opening-title">{{ $opening['welcome'] }} <em>{{ $opening['welcome_em'] }}</em></h1>
            <p class="opening-tagline">{{ $opening['tagline'] }}</p>

            <div class="opening-enter">
                @forelse ($venues as $venue)
                    <a href="{{ route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" class="opening-button" data-stage-exit>
                        <span class="journey-key" aria-hidden="true">I</span>
                        <span>{{ str_replace(':name', $venue->name, $opening['enter']) }}</span>
                        <span aria-hidden="true">→</span>
                    </a>
                @empty
                    <p class="opening-empty">{{ $opening['empty'] }}</p>
                @endforelse
            </div>
        </section>

        @if ($venues->count() === 1)
            @php
                $venueSlug = $venues->first()->slug;
                $openingLinks = [
                    'halls' => route('venue.halls', ['venueSlug' => $venueSlug, 'lang' => $locale]),
                    'services' => route('venue.services', ['venueSlug' => $venueSlug, 'lang' => $locale]),
                    'reservation' => route('venue.reservation', ['venueSlug' => $venueSlug, 'lang' => $locale]),
                    'staff' => route('venue.staff', ['venueSlug' => $venueSlug, 'lang' => $locale]),
                ];
            @endphp
            {{-- The order of the day, set out like the back of a wedding invitation. --}}
            <aside class="opening-directory" aria-label="{{ $opening['directory'] }}">
                <p class="opening-directory-title">{{ $opening['directory'] }}</p>
                <ul class="opening-links">
                    @foreach ($openingLinks as $key => $href)
                        <li><a data-stage-exit href="{{ $href }}"><span class="opening-links-chapter">{{ $chapters[$key] }}</span><span>{{ $opening[$key] }}</span><span aria-hidden="true">→</span></a></li>
                    @endforeach
                </ul>
            </aside>
        @endif
    </main>
</body>
</html>
