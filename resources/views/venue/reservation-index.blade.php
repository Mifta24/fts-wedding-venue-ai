<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :chapter="$chapter" :backdrop="$backdrop" :menu-items="$menuItems" :title="$lobby['reservation'].' · '.$venue->name">
    <div class="stage-panels">
        @include('venue.reservation')
    </div>
</x-venue-stage>
