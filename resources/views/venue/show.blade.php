@php
    $money = fn ($value) => $price($value);
    $hallsItem = collect($menuItems)->firstWhere('key', 'halls');
    $reservationItem = collect($menuItems)->firstWhere('key', 'reservation');
@endphp
<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :chapter="$chapter" :backdrop="$backdrop" :menu-items="$menuItems">
    <x-slot:welcome>
        <div class="stage-welcome">
            <span class="lobby-eyebrow">{{ $venue->name }} · {{ $venue->city }}</span>
            <h1>{{ $lobby['welcome'] }} <em>{{ $lobby['lobby'] }}.</em></h1>
            <p>{{ $lobby['intro'] }}</p>

            @if ($halls->isNotEmpty())
                <div class="lobby-cta">
                    <a href="{{ $hallsItem['href'] }}" class="lobby-action lobby-action-signal" data-stage-exit data-tour-line="{{ $hallsItem['tour'] }}" data-topic="{{ $hallsItem['topic'] }}">{{ $lobby['cta_halls'] }} <span aria-hidden="true">→</span></a>
                    <a href="{{ $reservationItem['href'] }}" class="lobby-action-ghost" data-stage-exit data-tour-line="{{ $reservationItem['tour'] }}" data-topic="{{ $reservationItem['topic'] }}">{{ $lobby['start_booking'] }}</a>
                </div>
            @endif

            @if ($videoId = $venue->videoId())
                <button type="button" class="lobby-video-btn" data-video-open aria-haspopup="dialog">
                    <span class="lobby-video-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M8 5.5v13a1 1 0 0 0 1.52.85l10.4-6.5a1 1 0 0 0 0-1.7L9.52 4.65A1 1 0 0 0 8 5.5Z"/></svg></span>
                    <span>{{ $lobby['watch_video'] }}</span>
                </button>
                <dialog class="video-modal" data-video-modal data-video-id="{{ $videoId }}" aria-label="{{ $lobby['video_label'] }}">
                    <button type="button" class="video-modal-close" data-video-close aria-label="{{ $lobby['close'] }}"><span aria-hidden="true">×</span></button>
                    <div class="video-modal-frame" data-video-frame></div>
                </dialog>
            @endif

            <dl class="lobby-stats">
                @if ($halls->isNotEmpty() && ! $venue->showsPrices())
                    @foreach ($venue->planInfoFor($locale)['stats'] ?? [] as [$statLabel, $statValue])
                        <div><dt>{{ $statLabel }}</dt><dd>{{ $statValue }}</dd></div>
                    @endforeach
                @elseif ($halls->isNotEmpty())
                    <div><dt>{{ $lobby['stat_from'] }}</dt><dd>{{ $money($halls->min('base_price')) }}</dd></div>
                    <div><dt>{{ $lobby['stat_capacity'] }}</dt><dd>{{ str_replace(':count', number_format((int) $halls->max('max_guests'), 0, ',', '.'), $lobby['stat_capacity_value']) }}</dd></div>
                @endif
                @if ($venue->weekday_discount_percent > 0)
                    <div><dt>{{ $lobby['stat_weekday'] }}</dt><dd>{{ str_replace(':percent', (string) $venue->weekday_discount_percent, $lobby['stat_weekday_value']) }}</dd></div>
                @endif
            </dl>
        </div>
    </x-slot:welcome>

    @php $films = $venue->filmsFor($locale); @endphp
    @if ($films)
        <div class="lobby-films" data-films>
            @foreach ($films as $film)
                <button type="button" class="lobby-film" data-film-open data-film-src="{{ $film['src'] }}" data-film-title="{{ $film['title'] }}" aria-haspopup="dialog" aria-label="{{ $lobby['watch_video'] }}: {{ $film['title'] }}" title="{{ $film['title'] }}">
                    @if ($film['poster'])<img src="{{ $film['poster'] }}" alt="" loading="lazy">@endif
                    <span class="lobby-film-play" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M8 5.5v13a1 1 0 0 0 1.52.85l10.4-6.5a1 1 0 0 0 0-1.7L9.52 4.65A1 1 0 0 0 8 5.5Z"/></svg></span>
                </button>
            @endforeach
        </div>
        <dialog class="video-modal" data-film-modal aria-label="{{ $lobby['video_label'] }}">
            <button type="button" class="video-modal-close" data-film-close aria-label="{{ $lobby['close'] }}"><span aria-hidden="true">×</span></button>
            <div class="video-modal-frame" data-film-frame></div>
        </dialog>
    @endif
    @php $lobbyPhotos = $venue->lobbyPhotosFor($locale); @endphp
    @if ($lobbyPhotos)
        <div class="lobby-photos" data-lobby-photos>
            @foreach ($lobbyPhotos as $i => $photo)
                <button type="button" class="lobby-photo" data-photo-open="{{ $i }}" aria-haspopup="dialog" aria-label="{{ $lobby['zoom_photo'] }}: {{ $photo['title'] }}" title="{{ $photo['title'] }}">
                    <img src="{{ $photo['thumb'] }}" alt="" loading="lazy" width="108" height="83">
                </button>
            @endforeach
        </div>
        <dialog class="lobby-photo-modal" data-photo-modal aria-label="{{ $lobby['photo_info'] }}">
            <button type="button" class="video-modal-close" data-photo-close aria-label="{{ $lobby['close'] }}"><span aria-hidden="true">×</span></button>
            <button type="button" class="hall-lightbox-nav lobby-photo-prev" data-photo-prev aria-label="{{ $lobby['prev_photo'] }}"><span aria-hidden="true">←</span></button>
            <button type="button" class="hall-lightbox-nav lobby-photo-next" data-photo-next aria-label="{{ $lobby['next_photo'] }}"><span aria-hidden="true">→</span></button>
            <img class="lobby-photo-big" data-photo-image alt="">
            <div class="lobby-photo-bar">
                <p data-photo-caption aria-live="polite"></p>
                <a class="hall-lightbox-detail" data-photo-link href="#">{{ $lobby['detail'] }} →</a>
            </div>
        </dialog>
        <script type="application/json" data-photo-data>@json($lobbyPhotos)</script>
    @endif
</x-venue-stage>
