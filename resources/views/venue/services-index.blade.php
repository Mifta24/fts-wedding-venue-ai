<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :chapter="$chapter" :backdrop="$backdrop" :menu-items="$menuItems" :title="$labels['menu_services'].' · '.$venue->name">
    <div class="stage-panels">
        <section class="lobby-content stage-panel-right @container" aria-label="{{ $labels['menu_services'] }}">
            <a href="{{ route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit data-tour-line="{{ $narration['tour_lobby'] }}" class="panel-close" aria-label="{{ $lobby['back'] }}"><span aria-hidden="true">×</span></a>
            <p class="lobby-eyebrow">{{ $lobby['chapter'] }} {{ $chapter }}</p>
            <h2>{{ $labels['menu_services'] }}</h2>
            @if ($sceneNarrations['services'])
                @include('venue.narrator', ['text' => $sceneNarrations['services'], 'key' => 'services', 'inline' => true])
            @endif

            <div class="service-grid">
                @forelse ($services as $item)
                    @php $title = $item->translatedTitle($locale); @endphp
                    <a href="{{ route('venue.service', ['venueSlug' => $venue->slug, 'serviceId' => $item->id, 'lang' => $locale]) }}" data-stage-exit class="service-card">
                        <span class="service-card-photo">
                            @if ($item->image_url)
                                <img src="{{ $item->image_url }}" alt="" loading="lazy">
                            @endif
                            <span class="service-card-icon"><x-service-icon :name="$item->iconName()" /></span>
                        </span>
                        <span class="service-card-body">
                            <span class="service-card-index">{{ $chapter }}.{{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="service-card-title">{{ $title }}</span>
                            <span class="service-card-text">{{ $item->translatedBody($locale) }}</span>
                        </span>
                        <span class="service-card-go" aria-hidden="true">›</span>
                    </a>
                @empty
                    <p class="text-stone-500">{{ $lobby['empty'] }}</p>
                @endforelse
            </div>
        </section>
    </div>
</x-venue-stage>
