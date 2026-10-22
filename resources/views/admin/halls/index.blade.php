<x-admin-layout title="Hall">
    <x-slot name="actions">
        <a href="{{ route('admin.halls.create') }}" class="rounded-lg bg-ink px-3 py-2 text-sm font-medium text-white hover:bg-gold hover:text-gold-ink">
            Tambah hall
        </a>
    </x-slot>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">Hall</th>
                    <th class="px-4 py-3">Suasana</th>
                    <th class="px-4 py-3">Tarif / acara</th>
                    <th class="px-4 py-3">Kapasitas</th>
                    <th class="px-4 py-3">Foto</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($halls as $hall)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($cover = $hall->images->first()?->image_source)
                                    <img src="{{ $cover }}" alt="{{ $hall->images->first()->alt_text ?? $hall->name }}" loading="lazy" class="h-12 w-16 shrink-0 rounded-md object-cover">
                                @else
                                    <span class="grid h-12 w-16 shrink-0 place-items-center rounded-md bg-stone-100 text-[10px] uppercase tracking-wide text-stone-400">Tanpa foto</span>
                                @endif
                                <span class="font-medium text-stone-900">{{ $hall->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ \App\Models\Hall::settingLabel($hall->setting) }}@if($hall->size_sqm)<span class="text-stone-400"> · {{ $hall->size_sqm }} m²</span>@endif</td>
                        <td class="px-4 py-3">{{ $venue->currency }} {{ number_format((float) $hall->base_price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">{{ $hall->min_guests }}–{{ $hall->max_guests }} tamu</td>
                        <td class="px-4 py-3">{{ $hall->images_count }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $hall->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                {{ $hall->is_active ? 'Aktif' : 'Tersembunyi' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <a href="{{ route('admin.halls.inventory.index', $hall) }}" class="text-stone-600 hover:underline">Kalender</a>
                            <a href="{{ route('admin.halls.edit', $hall) }}" class="ml-3 text-stone-600 hover:underline">Ubah</a>
                            <form method="POST" action="{{ route('admin.halls.destroy', $hall) }}" class="inline" onsubmit="return confirm('Hapus hall ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-stone-400">Belum ada hall.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
