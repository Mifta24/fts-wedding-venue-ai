@php
    $hallName = $hall->translatedName($locale);
    $money = fn ($value) => $venue->currency."\u{00A0}".number_format((float) $value, 0, ',', '.');
    $seating = collect($hall->seating_styles ?? [])
        ->map(fn ($style) => $hallTerms['seating'][$style] ?? Str::headline($style))
        ->implode(', ');
    $settingLabel = $hallTerms['setting'][$hall->setting] ?? Str::headline((string) $hall->setting);
    $primaryImage = $hall->images->first();
    $hallUrl = fn ($hall) => route('venue.hall', ['venueSlug' => $venue->slug, 'hallSlug' => $hall->slug, 'lang' => $locale]);
@endphp
<section class="lobby-content @container" aria-label="{{ $hallName }}">
    <a href="{{ route('venue.halls', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit class="panel-close" aria-label="{{ $labels['halls_heading'] }}"><span aria-hidden="true">×</span></a>
    <article class="hall-scene">
        <div class="flex flex-wrap items-center justify-between gap-3 pr-12">
            <p class="lobby-eyebrow">{{ $lobby['hall_counter'] }} {{ str_pad((string) ($hallIndex + 1), 2, '0', STR_PAD_LEFT) }} / {{ str_pad((string) $halls->count(), 2, '0', STR_PAD_LEFT) }}</p>
            @if ($halls->count() > 1)
                <nav class="flex gap-2" aria-label="{{ $lobby['hall_scene'] }}">
                    <a href="{{ $hallUrl($previousHall) }}" data-stage-exit class="hall-scene-step" rel="prev"><span aria-hidden="true">←</span> {{ $lobby['prev_hall'] }}</a>
                    <a href="{{ $hallUrl($nextHall) }}" data-stage-exit class="hall-scene-step" rel="next">{{ $lobby['next_hall'] }} <span aria-hidden="true">→</span></a>
                </nav>
            @endif
        </div>

        <div class="mt-4 grid gap-7 @2xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <div data-hall-gallery>
                <div class="hall-scene-photo">
                    @if ($primaryImage)
                        <button type="button" class="hall-scene-zoom" data-hall-zoom aria-label="{{ $lobby['zoom_photo'] }}" aria-haspopup="dialog">
                            <img src="{{ $primaryImage->image_source }}" alt="{{ $primaryImage->alt_text ?: $hallName }}" data-hall-gallery-main>
                            <span class="hall-scene-zoom-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.5-4.5M10.5 8v5M8 10.5h5"/></svg>
                            </span>
                        </button>
                    @else
                        <div class="grid h-full place-items-center text-sm">{{ $lobby['no_photo'] }}</div>
                    @endif
                    <span class="hall-scene-badge">{{ $settingLabel }}</span>
                </div>
                @if ($hall->images->count() > 1)
                    <ul class="mt-2 grid grid-cols-4 gap-2" aria-label="{{ $lobby['gallery'] }}">
                        @foreach ($hall->images as $imageIndex => $image)
                            <li>
                                <button type="button" class="hall-scene-thumb" data-hall-thumb data-src="{{ $image->image_source }}" data-alt="{{ $image->alt_text ?: $hallName }}" aria-label="{{ $lobby['photo'] }} {{ $imageIndex + 1 }}" @if($imageIndex === 0) aria-current="true" @endif>
                                    <img src="{{ $image->image_source }}" alt="" class="h-full w-full object-cover" loading="lazy">
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($primaryImage)
                    <dialog class="hall-lightbox" data-hall-lightbox aria-label="{{ $hallName }}">
                        <button type="button" class="hall-lightbox-close" data-lightbox-close aria-label="{{ $lobby['close'] }}"><span aria-hidden="true">×</span></button>
                        @if ($hall->images->count() > 1)
                            <button type="button" class="hall-lightbox-nav hall-lightbox-prev" data-lightbox-prev aria-label="{{ $lobby['prev_photo'] }}"><span aria-hidden="true">←</span></button>
                            <button type="button" class="hall-lightbox-nav hall-lightbox-next" data-lightbox-next aria-label="{{ $lobby['next_photo'] }}"><span aria-hidden="true">→</span></button>
                        @endif
                        <img src="" alt="" data-lightbox-image>
                        <p class="hall-lightbox-caption" data-lightbox-caption aria-live="polite"></p>
                    </dialog>
                @endif

                @include('venue.narrator', ['text' => $hallNarrations['hall'][$hall->slug], 'key' => 'hall-'.$hall->slug])
            </div>

            <div class="min-w-0">
                <h2 class="mt-0!">{{ $hallName }}</h2>
                <p class="hall-scene-description">{{ $hall->translatedDescription($locale) }}</p>

                <div class="hall-scene-rate">
                    <p><span class="hall-scene-rate-label">{{ $lobby['rate_label'] }}</span> <span class="hall-scene-rate-amount"><strong>{{ $money($hall->base_price) }}</strong> <span class="hall-scene-rate-label">{{ $lobby['rate_per_event'] }}</span></span></p>
                    @if ($venue->weekday_discount_percent > 0)
                        <p class="hall-scene-monthly">{{ str_replace(':percent', (string) $venue->weekday_discount_percent, $lobby['weekday_note']) }}</p>
                    @endif
                    @if ($venue->deposit_percent > 0)
                        <p class="hall-scene-monthly">{{ str_replace(':percent', (string) $venue->deposit_percent, $lobby['deposit_note']) }}</p>
                    @endif
                    <p class="hall-scene-note">{{ $lobby['availability_note'] }}</p>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('venue.reservation', ['venueSlug' => $venue->slug, 'lang' => $locale, 'hall' => $hall->slug]) }}" data-stage-exit data-tour-line="{{ $narration['tour_reservation'] }}" class="lobby-action">{{ $lobby['reserve_hall'] }} <span aria-hidden="true">↗</span></a>
                    <button type="button" class="hall-scene-step" data-hero-quick-message="{{ str_replace(':name', $hallName, $lobby['ask_hall_q']) }}">{{ $lobby['ask_hall'] }}</button>
                </div>

                <dl class="hall-scene-facts">
                    <div><dt>{{ $lobby['capacity'] }}</dt><dd>{{ $hall->min_guests }}–{{ $hall->max_guests }} {{ $lobby['guests_unit'] }}</dd></div>
                    @if ($hall->size_sqm)
                        <div><dt>{{ $lobby['size'] }}</dt><dd>{{ $hall->size_sqm }} m²</dd></div>
                    @endif
                    <div><dt>{{ $lobby['setting'] }}</dt><dd>{{ $settingLabel }}</dd></div>
                    @if ($hall->view_type)
                        <div><dt>{{ $lobby['view'] }}</dt><dd>{{ $hallTerms['view'][$hall->view_type] ?? Str::headline($hall->view_type) }}</dd></div>
                    @endif
                    @if ($seating !== '')
                        <div class="hall-scene-fact-wide"><dt>{{ $lobby['seating'] }}</dt><dd>{{ $seating }}</dd></div>
                    @endif
                    <div><dt>{{ $lobby['catering'] }}</dt><dd>{{ $hall->catering_included ? $lobby['catering_included'] : $lobby['catering_excluded'] }}</dd></div>
                    <div>
                        <dt>{{ $lobby['extra_hours'] }}</dt>
                        <dd>
                            @if ($hall->extra_hour_available)
                                {{ str_replace(':price', $money($hall->extra_hour_price), $lobby['extra_hours_yes']) }}
                            @else
                                {{ $lobby['extra_hours_no'] }}
                            @endif
                        </dd>
                    </div>
                </dl>

                @if (! empty($hall->amenities))
                    <h3 class="hall-scene-subheading">{{ $lobby['amenities'] }}</h3>
                    <ul class="hall-scene-amenities">
                        @foreach ($hall->amenities as $amenity)
                            <li>{{ $hallTerms['amenity'][$amenity] ?? Str::headline($amenity) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </article>
</section>
