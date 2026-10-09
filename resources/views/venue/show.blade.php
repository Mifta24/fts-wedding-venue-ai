@php
    $money = fn ($value) => $venue->currency.' '.number_format((float) $value, 0, ',', '.');
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

            <dl class="lobby-stats">
                @if ($halls->isNotEmpty())
                    <div><dt>{{ $lobby['stat_from'] }}</dt><dd>{{ $money($halls->min('base_price')) }}</dd></div>
                    <div><dt>{{ $lobby['stat_capacity'] }}</dt><dd>{{ str_replace(':count', number_format((int) $halls->max('max_guests'), 0, ',', '.'), $lobby['stat_capacity_value']) }}</dd></div>
                @endif
                @if ($venue->weekday_discount_percent > 0)
                    <div><dt>{{ $lobby['stat_weekday'] }}</dt><dd>{{ str_replace(':percent', (string) $venue->weekday_discount_percent, $lobby['stat_weekday_value']) }}</dd></div>
                @endif
            </dl>
        </div>
    </x-slot:welcome>
</x-venue-stage>
