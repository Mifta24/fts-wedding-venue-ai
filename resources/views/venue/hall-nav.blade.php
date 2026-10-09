{{-- The hall directory board: every hall with its setting, capacity and rental rate, so the couple can step between halls without going back. --}}
<aside class="hall-directory" aria-label="{{ $labels['halls_heading'] }}">
    <p class="hall-directory-title"><span>{{ $labels['halls_heading'] }}</span><span>{{ $halls->count() }}</span></p>
    <nav class="hall-nav">
        @foreach ($halls as $item)
            <a href="{{ route('venue.hall', ['venueSlug' => $venue->slug, 'hallSlug' => $item->slug, 'lang' => $locale]) }}"
                data-stage-exit
                @if (($hall ?? null)?->slug === $item->slug) aria-current="page" @endif>
                @if ($item->images->first())
                    <img class="hall-nav-thumb" src="{{ $item->images->first()->image_source }}" alt="" loading="lazy">
                @endif
                <span class="hall-nav-text">
                    <span class="hall-nav-layout">{{ $hallTerms['setting'][$item->setting] ?? $item->setting }} · {{ $item->min_guests }}–{{ $item->max_guests }}</span>
                    <span class="hall-nav-name-row">
                        <span class="hall-nav-name">{{ $item->translatedName($locale) }}</span>
                        @if ($item->size_sqm)<span class="hall-nav-size">{{ $item->size_sqm }}&nbsp;m²</span>@endif
                    </span>
                    <span class="hall-nav-price">{{ $labels['from'] }} {{ $venue->currency }}&nbsp;{{ number_format((float) $item->base_price, 0, ',', '.') }} {{ $labels['per_event'] }}</span>
                </span>
                <span class="hall-nav-go" aria-hidden="true">→</span>
            </a>
        @endforeach
    </nav>
</aside>
