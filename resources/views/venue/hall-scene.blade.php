@php
    $hallName = $hall->translatedName($locale);
    $money = fn ($value) => $price($value, "\u{00A0}");
    $seating = collect($hall->seating_styles ?? [])
        ->map(fn ($style) => $hallTerms['seating'][$style] ?? Str::headline($style))
        ->implode(', ');
    $settingLabel = $hallTerms['setting'][$hall->setting] ?? Str::headline((string) $hall->setting);
    $primaryImage = $hall->images->first();
    $tagLabels = fn ($image) => collect($image->tags ?? [])->map(fn ($tag) => $hallTerms['photo_tag'][$tag] ?? null)->filter()->values();
    $hallUrl = fn ($hall) => route('venue.hall', ['venueSlug' => $venue->slug, 'hallSlug' => $hall->slug, 'lang' => $locale]);
@endphp
<section class="lobby-content @container" aria-label="{{ $hallName }}">
    <a href="{{ route('venue.halls', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit data-stage-inner class="panel-close" aria-label="{{ $labels['halls_heading'] }}"><span aria-hidden="true">×</span></a>
    <article class="hall-scene">
        <div class="flex flex-wrap items-center justify-between gap-3 pr-12">
            <p class="lobby-eyebrow">{{ $lobby['hall_counter'] }} {{ str_pad((string) ($hallIndex + 1), 2, '0', STR_PAD_LEFT) }} / {{ str_pad((string) $halls->count(), 2, '0', STR_PAD_LEFT) }}</p>
            @if ($halls->count() > 1)
                <nav class="flex gap-2" aria-label="{{ $lobby['hall_scene'] }}">
                    <a href="{{ $hallUrl($previousHall) }}" data-stage-exit data-stage-inner class="hall-scene-step" rel="prev"><span aria-hidden="true">←</span> {{ $lobby['prev_hall'] }}</a>
                    <a href="{{ $hallUrl($nextHall) }}" data-stage-exit data-stage-inner class="hall-scene-step" rel="next">{{ $lobby['next_hall'] }} <span aria-hidden="true">→</span></a>
                </nav>
            @endif
        </div>

        <div class="mt-4 grid gap-7 @2xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <div data-hall-gallery>
                <div class="hall-scene-photo">
                    @if ($primaryImage)
                        <button type="button" class="hall-scene-zoom" data-hall-zoom aria-label="{{ $lobby['zoom_photo'] }}" aria-haspopup="dialog">
                            <img src="{{ $primaryImage->image_source }}" alt="{{ $primaryImage->alt_text ?: $hallName }}" data-tags="{{ $tagLabels($primaryImage)->toJson(JSON_UNESCAPED_UNICODE) }}" data-hall-gallery-main>
                            <span class="hall-scene-zoom-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.5-4.5M10.5 8v5M8 10.5h5"/></svg>
                            </span>
                        </button>
                    @else
                        <div class="grid h-full place-items-center text-sm">{{ $lobby['no_photo'] }}</div>
                    @endif
                    @unless ($venue->isPlanCatalog())<span class="hall-scene-badge">{{ $settingLabel }}</span>@endunless
                </div>
                @if ($hall->images->count() > 1)
                    <ul class="mt-2 grid grid-cols-4 gap-2" aria-label="{{ $lobby['gallery'] }}">
                        @foreach ($hall->images as $imageIndex => $image)
                            <li>
                                <button type="button" class="hall-scene-thumb" data-hall-thumb data-src="{{ $image->image_source }}" data-alt="{{ $image->alt_text ?: $hallName }}" data-tags="{{ $tagLabels($image)->toJson(JSON_UNESCAPED_UNICODE) }}" aria-label="{{ $lobby['photo'] }} {{ $imageIndex + 1 }}" @if($imageIndex === 0) aria-current="true" @endif>
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
                        <div class="hall-lightbox-bar">
                            <p class="hall-lightbox-caption" data-lightbox-caption aria-live="polite"></p>
                            <button type="button" class="hall-lightbox-detail" data-lightbox-detail aria-expanded="false" aria-controls="lightbox-info-{{ $hall->id }}">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                                <span>{{ $lobby['detail'] }}</span>
                            </button>
                        </div>
                        <section class="hall-lightbox-info" id="lightbox-info-{{ $hall->id }}" data-lightbox-info hidden aria-label="{{ $lobby['photo_info'] }}">
                            <div class="hall-lightbox-info-photo">
                                <p class="hall-lightbox-info-eyebrow">{{ $lobby['photo_of'] }} <span data-info-count></span></p>
                                <p class="hall-lightbox-info-title" data-info-title></p>
                                <ul class="hall-lightbox-tags" data-info-tags></ul>
                            </div>
                            <dl class="hall-lightbox-info-facts">
                                <div><dt>{{ $lobby['hall_label'] }}</dt><dd>{{ $hallName }}</dd></div>
                                @unless ($venue->isPlanCatalog())
                                    <div><dt>{{ $lobby['capacity'] }}</dt><dd>{{ $hall->min_guests }}–{{ $hall->max_guests }} {{ $lobby['guests_unit'] }}</dd></div>
                                @endunless
                                @if ($hall->size_sqm)
                                    <div><dt>{{ $lobby['size'] }}</dt><dd>{{ $hall->size_sqm }} m²</dd></div>
                                @endif
                                @unless ($venue->isPlanCatalog())
                                    <div><dt>{{ $lobby['setting'] }}</dt><dd>{{ $settingLabel }}</dd></div>
                                @endunless
                                @if ($hall->view_type)
                                    <div><dt>{{ $lobby['view'] }}</dt><dd>{{ $hallTerms['view'][$hall->view_type] ?? Str::headline($hall->view_type) }}</dd></div>
                                @endif
                            </dl>
                        </section>
                    </dialog>
                @endif

                @include('venue.narrator', ['text' => $hallNarrations['hall'][$hall->slug], 'key' => 'hall-'.$hall->slug])
            </div>

            <div class="min-w-0">
                <h2 class="mt-0!">{{ $hallName }}</h2>
                <p class="hall-scene-description">{{ $hall->translatedDescription($locale) }}</p>

                <div class="hall-scene-rate">
                    <p><span class="hall-scene-rate-label">{{ $venue->isPlanCatalog() ? ($venue->planInfoFor($locale)['rate_label'] ?? $lobby['rate_label']) : $lobby['rate_label'] }}</span> <span class="hall-scene-rate-amount"><strong>{{ $money($hall->base_price) }}</strong> <span class="hall-scene-rate-label">{{ $venue->isPlanCatalog() ? ($venue->planInfoFor($locale)['per_event'] ?? $lobby['rate_per_event']) : $lobby['rate_per_event'] }}</span></span></p>
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

                @if ($venue->isPlanCatalog())
                    @php $info = $venue->planInfoFor($locale); @endphp
                    @if (! empty($hall->price_options))
                        <div class="plan-block">
                            <h3 class="hall-scene-subheading">{{ $info['prices_heading'] ?? '' }}</h3>
                            <table class="plan-prices">
                                <thead><tr><th scope="col">{{ $info['plan_col'] ?? '' }}</th><th scope="col">{{ $info['price_col'] ?? '' }}</th></tr></thead>
                                <tbody>
                                    @foreach ($hall->price_options as $option)
                                        <tr><td>{{ $info['options'][$option['key']] ?? $option['key'] }}</td><td><strong>{{ $money($option['price']) }}</strong></td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if (! empty($info['tax_note']))<p class="plan-note">{{ $info['tax_note'] }} {{ $info['flat_rate'] ?? '' }}</p>@endif
                        </div>
                    @endif
                    @if (! empty($info['includes']))
                        <div class="plan-block">
                            <h3 class="hall-scene-subheading">{{ $info['includes_heading'] ?? '' }}</h3>
                            <ul class="plan-list">@foreach ($info['includes'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                        </div>
                    @endif
                    @if (! empty($info['extras']))
                        <div class="plan-block">
                            <h3 class="hall-scene-subheading">{{ $info['extras_heading'] ?? '' }}</h3>
                            <ul class="plan-list is-extra">@foreach ($info['extras'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                        </div>
                    @endif
                    @if (! empty($info['early_bird']['title']))
                        <div class="plan-bonus"><strong>{{ $info['early_bird']['title'] }}</strong><p>{{ $info['early_bird']['body'] }}</p></div>
                    @endif
                @else
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
                @endif
            </div>
        </div>
    </article>
</section>
