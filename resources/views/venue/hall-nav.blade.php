{{-- The hall directory board: every hall with its setting, capacity and rental rate, so the couple can step between halls without going back.
     A venue that groups its halls (e.g. by area) gets area buttons on top; only the chosen area's halls are listed below. This is limited to this board. --}}
@php
    $groups = $venue->hallGroupOptions($locale);
    $planInfo = $venue->isPlanCatalog() ? $venue->planInfoFor($locale) : [];
    $activeGroup = (($hall ?? null)?->group_key) ?: ($groups[0]['key'] ?? null);
    $inGroup = fn ($item) => ! $groups || $item->group_key === $activeGroup;
    $groupCounts = $halls->groupBy('group_key')->map->count();
@endphp
<aside class="hall-directory" aria-label="{{ $labels['halls_heading'] }}" @if($groups) data-area-board @endif>
    <p class="hall-directory-title"><span>{{ $labels['halls_heading'] }}</span><span data-area-count>{{ $groups ? ($groupCounts[$activeGroup] ?? 0) : $halls->count() }}</span></p>
    @if ($groups)
        <div class="area-tabs" role="tablist" aria-label="{{ $labels['halls_heading'] }}">
            @foreach ($groups as $group)
                <button type="button" role="tab" class="area-tab" id="area-tab-{{ $group['key'] }}" data-area-tab="{{ $group['key'] }}"
                    aria-selected="{{ $group['key'] === $activeGroup ? 'true' : 'false' }}" aria-controls="hall-nav-list" tabindex="{{ $group['key'] === $activeGroup ? '0' : '-1' }}">
                    <span class="area-tab-label">{{ $group['label'] }}</span><span class="area-tab-count" aria-hidden="true">{{ $groupCounts[$group['key']] ?? 0 }}</span>
                </button>
            @endforeach
        </div>
    @endif
    <nav class="hall-nav"@if($groups) id="hall-nav-list" role="tabpanel" aria-labelledby="area-tab-{{ $activeGroup }}"@endif>
        @foreach ($halls as $item)
            <a href="{{ route('venue.hall', ['venueSlug' => $venue->slug, 'hallSlug' => $item->slug, 'lang' => $locale]) }}"
                data-stage-exit data-stage-inner @if($groups) data-area="{{ $item->group_key }}" @endif
                @if (! $inGroup($item)) hidden @endif
                @if (($hall ?? null)?->slug === $item->slug) aria-current="page" @endif>
                @if ($item->images->first())
                    <img class="hall-nav-thumb" src="{{ $item->images->first()->image_source }}" alt="" loading="lazy">
                @endif
                <span class="hall-nav-text">
                    @if ($venue->isPlanCatalog())
                        <span class="hall-nav-layout">{{ collect($groups)->firstWhere('key', $item->group_key)['label'] ?? '' }}</span>
                    @else
                        <span class="hall-nav-layout">{{ $hallTerms['setting'][$item->setting] ?? $item->setting }} · {{ $item->min_guests }}–{{ $item->max_guests }}</span>
                    @endif
                    <span class="hall-nav-name-row">
                        <span class="hall-nav-name">{{ $item->translatedName($locale) }}</span>
                        @if ($item->size_sqm && ! $venue->isPlanCatalog())<span class="hall-nav-size">{{ $item->size_sqm }}&nbsp;m²</span>@endif
                    </span>
                    <span class="hall-nav-price">{{ $labels['from'] }} {{ $price($item->base_price, "\u{00A0}") }} {{ $planInfo['per_event'] ?? $labels['per_event'] }}</span>
                </span>
                <span class="hall-nav-go" aria-hidden="true">→</span>
            </a>
        @endforeach
    </nav>
</aside>
