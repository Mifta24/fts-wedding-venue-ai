<x-admin-layout title="Halls">
    <x-slot name="actions">
        <a href="{{ route('admin.halls.create') }}" class="rounded-lg bg-ink px-3 py-2 text-sm font-medium text-white hover:bg-gold hover:text-gold-ink">
            Add hall
        </a>
    </x-slot>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Setting</th>
                    <th class="px-4 py-3">Rate / event</th>
                    <th class="px-4 py-3">Capacity</th>
                    <th class="px-4 py-3">Images</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($halls as $hall)
                    <tr>
                        <td class="px-4 py-3 font-medium text-stone-900">{{ $hall->name }}</td>
                        <td class="px-4 py-3">{{ ucwords(str_replace('_', '-', $hall->setting)) }}@if($hall->size_sqm)<span class="text-stone-400"> · {{ $hall->size_sqm }} m²</span>@endif</td>
                        <td class="px-4 py-3">{{ $venue->currency }} {{ number_format((float) $hall->base_price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">{{ $hall->min_guests }}–{{ $hall->max_guests }} guests</td>
                        <td class="px-4 py-3">{{ $hall->images_count }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $hall->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                {{ $hall->is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.halls.inventory.index', $hall) }}" class="text-stone-600 hover:underline">Calendar</a>
                            <a href="{{ route('admin.halls.edit', $hall) }}" class="ml-3 text-stone-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.halls.destroy', $hall) }}" class="inline" onsubmit="return confirm('Delete this hall?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-stone-400">No halls yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
