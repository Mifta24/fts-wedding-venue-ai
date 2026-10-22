<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :chapter="$chapter" :backdrop="$backdrop" :menu-items="$menuItems" :title="$hall->translatedName($locale).' · '.$venue->name">
    @if($halls->isNotEmpty())
        <x-slot:sidebar>@include('venue.hall-nav')</x-slot:sidebar>
    @endif

    <div class="stage-panels">
        @include('venue.hall-scene')
    </div>
</x-venue-stage>
