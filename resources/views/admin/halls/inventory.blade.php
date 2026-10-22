<x-admin-layout :title="'Kalender — '.$hall->name">
    <x-slot name="actions">
        <a href="{{ route('admin.halls.index') }}" class="text-sm text-stone-600 hover:underline">Kembali ke daftar hall</a>
    </x-slot>

    <form method="POST" action="{{ route('admin.halls.inventory.store', $hall) }}" class="rounded-xl border border-stone-200 bg-white p-5">
        @csrf
        <h2 class="text-sm font-semibold text-stone-900">Buka tanggal pernikahan dan atur harganya</h2>
        <p class="mt-1 text-xs text-stone-500">Berlaku untuk setiap tanggal dari awal sampai akhir. Kosongkan harga untuk mempertahankan harga saat ini (tanggal baru memakai tarif dasar {{ $venue->currency }} {{ number_format((float) $hall->base_price, 0, ',', '.') }}).</p>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            <div>
                <label for="from" class="block text-sm font-medium text-stone-700">Dari</label>
                <input id="from" type="date" name="from" value="{{ old('from', $today) }}" min="{{ $today }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="to" class="block text-sm font-medium text-stone-700">Sampai (tanggal terakhir)</label>
                <input id="to" type="date" name="to" value="{{ old('to') }}" min="{{ $today }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="total_slots" class="block text-sm font-medium text-stone-700">Acara per tanggal</label>
                <input id="total_slots" type="number" name="total_slots" value="{{ old('total_slots', 1) }}" min="0" max="50" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="price" class="block text-sm font-medium text-stone-700">Tarif per acara ({{ $venue->currency }})</label>
                <input id="price" type="number" name="price" value="{{ old('price') }}" min="0" step="1" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button type="submit" class="rounded-lg bg-ink px-4 py-2 text-sm font-medium text-white hover:bg-gold hover:text-gold-ink">Simpan kalender</button>
        </div>
    </form>

    <div class="mt-8 mb-3 flex items-center justify-between gap-2 text-sm">
        <a href="{{ route('admin.halls.inventory.index', [$hall, 'month' => $month->subMonth()->format('Y-m')]) }}" class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-stone-700 hover:bg-stone-100" aria-label="{{ $month->subMonth()->locale('id')->translatedFormat('F Y') }}">← <span class="hidden sm:inline">{{ $month->subMonth()->locale('id')->translatedFormat('F Y') }}</span></a>
        <h2 class="font-semibold text-stone-900">{{ $month->locale('id')->translatedFormat('F Y') }}</h2>
        <a href="{{ route('admin.halls.inventory.index', [$hall, 'month' => $month->addMonth()->format('Y-m')]) }}" class="rounded-lg border border-stone-300 bg-white px-3 py-2 text-stone-700 hover:bg-stone-100" aria-label="{{ $month->addMonth()->locale('id')->translatedFormat('F Y') }}"><span class="hidden sm:inline">{{ $month->addMonth()->locale('id')->translatedFormat('F Y') }}</span> →</a>
    </div>

    <p class="mb-2 text-xs text-stone-500">Ketuk satu hari untuk mengisi formulir di atas, lalu ketuk hari berikutnya untuk menentukan akhir rentang.</p>

    <div class="grid grid-cols-7 gap-1 text-center font-label text-[11px] uppercase tracking-wider text-stone-500">
        @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $weekday)
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
                aria-label="{{ $day->locale('id')->translatedFormat('l j F') }}: {{ $slot ? $free.' dari '.$slot->total_slots.' tersedia' : 'belum dibuka untuk reservasi' }}"
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
        <li><span class="mr-1 inline-block size-2.5 rounded-sm border border-emerald-200 bg-emerald-50 align-middle"></span>Terbuka</li>
        <li><span class="mr-1 inline-block size-2.5 rounded-sm border border-amber-200 bg-amber-50 align-middle"></span>Hampir penuh</li>
        <li><span class="mr-1 inline-block size-2.5 rounded-sm border border-red-200 bg-red-50 align-middle"></span>Penuh</li>
        <li><span class="mr-1 inline-block size-2.5 rounded-sm border border-stone-200 bg-stone-100 align-middle"></span>Belum dibuka</li>
        <li class="font-label">tersedia / acara per tanggal</li>
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
