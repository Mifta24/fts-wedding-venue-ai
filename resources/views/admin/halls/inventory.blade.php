<x-admin-layout :title="'Calendar — '.$hall->name">
    <x-slot name="actions">
        <a href="{{ route('admin.halls.index') }}" class="text-sm text-stone-600 hover:underline">Back to halls</a>
    </x-slot>

    <form method="POST" action="{{ route('admin.halls.inventory.store', $hall) }}" class="rounded-xl border border-stone-200 bg-white p-5">
        @csrf
        <h2 class="text-sm font-semibold text-stone-900">Open wedding dates and set the price</h2>
        <p class="mt-1 text-xs text-stone-500">Applies to every date from the first to the last. Leave the price empty to keep current prices (new dates use the base rate of {{ $venue->currency }} {{ number_format((float) $hall->base_price, 0, ',', '.') }}).</p>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            <div>
                <label for="from" class="block text-sm font-medium text-stone-700">From</label>
                <input id="from" type="date" name="from" value="{{ old('from', $today) }}" min="{{ $today }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="to" class="block text-sm font-medium text-stone-700">To (last date)</label>
                <input id="to" type="date" name="to" value="{{ old('to') }}" min="{{ $today }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="total_slots" class="block text-sm font-medium text-stone-700">Events per date</label>
                <input id="total_slots" type="number" name="total_slots" value="{{ old('total_slots', 1) }}" min="0" max="50" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="price" class="block text-sm font-medium text-stone-700">Rate per event ({{ $venue->currency }})</label>
                <input id="price" type="number" name="price" value="{{ old('price') }}" min="0" step="1" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button type="submit" class="rounded-lg bg-ink px-4 py-2 text-sm font-medium text-white hover:bg-gold hover:text-gold-ink">Save calendar</button>
        </div>
    </form>

    <div class="mt-8 mb-3 flex items-center justify-between gap-2 text-sm">
        <a href="{{ route('admin.halls.inventory.index', [$hall, 'month' => $month->subMonth()->format('Y-m')]) }}" class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-stone-700 hover:bg-stone-100" aria-label="{{ $month->subMonth()->format('F Y') }}">← <span class="hidden sm:inline">{{ $month->subMonth()->format('F Y') }}</span></a>
        <h2 class="font-semibold text-stone-900">{{ $month->format('F Y') }}</h2>
        <a href="{{ route('admin.halls.inventory.index', [$hall, 'month' => $month->addMonth()->format('Y-m')]) }}" class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-stone-700 hover:bg-stone-100" aria-label="{{ $month->addMonth()->format('F Y') }}"><span class="hidden sm:inline">{{ $month->addMonth()->format('F Y') }}</span> →</a>
    </div>

    <p class="mb-2 text-xs text-stone-500">Tap a day to fill the form above, then tap a later day to choose the end of the range.</p>

    <div class="grid grid-cols-7 gap-1 text-center font-label text-[11px] uppercase tracking-wider text-stone-500">
        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
            <span class="py-1">{{ $weekday }}</span>
        @endforeach
    </div>

    <div class="mt-1 grid grid-cols-7 gap-1" data-inventory-grid>
        @for ($blank = 1; $blank < $month->dayOfWeekIso; $blank++)
            <span aria-hidden="true"></span>
        @endfor

        @foreach ($days as $day)
            @php
                $date = $day->toDateString();
                $slot = $inventory->get($date);
                $isPast = $date < $today;
                $free = $slot?->availableSlots();
                $tone = match (true) {
                    ! $slot => 'border-stone-200 bg-stone-100 text-stone-400',
                    $free === 0 => 'border-red-200 bg-red-50 text-red-700',
                    $slot->total_slots > 0 && $free / $slot->total_slots <= 0.25 => 'border-amber-200 bg-amber-50 text-amber-800',
                    default => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                };
            @endphp
            <button
                type="button"
                @disabled($isPast)
                data-day="{{ $date }}"
                @if ($slot) data-total="{{ $slot->total_slots }}" data-price="{{ (int) $slot->price }}" @endif
                aria-label="{{ $day->format('l j F') }}: {{ $slot ? $free.' of '.$slot->total_slots.' free' : 'not open for booking' }}"
                class="flex min-h-14 flex-col items-start justify-between rounded-lg border p-1.5 text-left transition sm:min-h-20 sm:p-2 {{ $tone }} {{ $isPast ? 'opacity-40' : 'hover:ring-2 hover:ring-ink/40' }} data-[picked]:ring-2 data-[picked]:ring-ink"
            >
                <span class="text-xs font-semibold sm:text-sm">{{ $day->day }}</span>
                @if ($slot)
                    <span class="font-label text-[11px] leading-tight">{{ $free }}/{{ $slot->total_slots }}</span>
                    <span class="hidden text-[11px] leading-tight opacity-80 sm:block">{{ number_format((float) $slot->price, 0, ',', '.') }}</span>
                @else
                    <span class="text-[11px] opacity-70">—</span>
                @endif
            </button>
        @endforeach
    </div>

    <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-stone-500">
        <li><span class="mr-1 inline-block size-2.5 rounded-sm border border-emerald-200 bg-emerald-50 align-middle"></span>Open</li>
        <li><span class="mr-1 inline-block size-2.5 rounded-sm border border-amber-200 bg-amber-50 align-middle"></span>Almost full</li>
        <li><span class="mr-1 inline-block size-2.5 rounded-sm border border-red-200 bg-red-50 align-middle"></span>Full</li>
        <li><span class="mr-1 inline-block size-2.5 rounded-sm border border-stone-200 bg-stone-100 align-middle"></span>Not open</li>
        <li class="font-label">free / events per date</li>
    </ul>

    <script>
        (() => {
            const grid = document.querySelector('[data-inventory-grid]');
            const from = document.getElementById('from');
            const to = document.getElementById('to');
            if (!grid || !from || !to) return;

            let picking = false;

            const mark = () => grid.querySelectorAll('[data-day]').forEach((cell) => {
                const day = cell.dataset.day;
                const inRange = from.value && to.value && day >= from.value && day <= to.value;
                cell.toggleAttribute('data-picked', Boolean(inRange));
            });

            grid.addEventListener('click', (event) => {
                const cell = event.target.closest('[data-day]');
                if (!cell || cell.disabled) return;

                if (!picking || cell.dataset.day < from.value) {
                    from.value = to.value = cell.dataset.day;
                    if (cell.dataset.total) document.getElementById('total_slots').value = cell.dataset.total;
                    if (cell.dataset.price) document.getElementById('price').value = cell.dataset.price;
                    picking = true;
                } else {
                    to.value = cell.dataset.day;
                    picking = false;
                }

                mark();
                from.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });

            [from, to].forEach((input) => input.addEventListener('change', mark));
        })();
    </script>
</x-admin-layout>
