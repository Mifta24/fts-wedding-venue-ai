<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :chapter="$chapter" :backdrop="$backdrop" :menu-items="$menuItems" :title="$labels['halls_heading'].' · '.$venue->name">
    <x-slot:welcome>
        <div class="stage-welcome stage-welcome-scene">
            <span class="lobby-eyebrow">{{ $lobby['chapter'] }} {{ $chapter }}</span>
            <h1>{{ $labels['halls_heading'] }}</h1>
            @if ($hallNarrations['halls'])
                @include('venue.narrator', ['text' => $hallNarrations['halls'], 'key' => 'halls', 'onStage' => true])
            @else
                <p>{{ $lobby['halls_empty'] }}</p>
            @endif
        </div>
    </x-slot:welcome>

    @if ($halls->isNotEmpty())
        <x-slot:sidebar>@include('venue.hall-nav')</x-slot:sidebar>
    @endif
</x-venue-stage>
