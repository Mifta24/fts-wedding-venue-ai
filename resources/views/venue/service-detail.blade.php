<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :chapter="$chapter" :backdrop="$backdrop" :menu-items="$menuItems" :title="$service->translatedTitle($locale).' · '.$venue->name">
    @php
        $title = $service->translatedTitle($locale);
        $serviceUrl = fn ($item) => route('venue.service', ['venueSlug' => $venue->slug, 'serviceId' => $item->id, 'lang' => $locale]);
    @endphp

    <div class="stage-panels">
        <section class="lobby-content stage-panel-right @container" aria-label="{{ $title }}">
            <a href="{{ route('venue.services', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit class="panel-close" aria-label="{{ $lobby['back_services'] }}"><span aria-hidden="true">×</span></a>
            <article class="hall-scene">
                <div class="flex flex-wrap items-center justify-between gap-3 pr-12">
                    <p class="lobby-eyebrow">{{ $lobby['chapter'] }} {{ $chapter }} · {{ $lobby['service_counter'] }} {{ $serviceIndex + 1 }} / {{ $services->count() }}</p>
                    @if ($services->count() > 1)
                        <nav class="flex gap-2" aria-label="{{ $labels['menu_services'] }}">
                            <a href="{{ $serviceUrl($previousService) }}" data-stage-exit class="hall-scene-step" rel="prev"><span aria-hidden="true">←</span> {{ $lobby['prev_service'] }}</a>
                            <a href="{{ $serviceUrl($nextService) }}" data-stage-exit class="hall-scene-step" rel="next">{{ $lobby['next_service'] }} <span aria-hidden="true">→</span></a>
                        </nav>
                    @endif
                </div>
                @if ($service->image_url)
                    <div class="service-hero mt-5">
                        <img src="{{ $service->image_url }}" alt="{{ $title }}">
                        <span class="service-card-icon"><x-service-icon :name="$service->iconName()" /></span>
                    </div>
                @endif
                <h2>{{ $title }}</h2>
                @include('venue.narrator', ['text' => $service->translatedBody($locale), 'key' => 'service-'.$service->id, 'inline' => true])
                <div class="mt-8 flex flex-wrap gap-3">
                    <button type="button" class="lobby-action" data-hero-quick-message="{{ str_replace(':name', $title, $lobby['ask_service_q']) }}">{{ $lobby['ask_service'] }} <span aria-hidden="true">↗</span></button>
                    <a href="{{ route('venue.services', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit class="hall-scene-step">{{ $lobby['back_services'] }}</a>
                </div>
            </article>
        </section>
    </div>
</x-venue-stage>
