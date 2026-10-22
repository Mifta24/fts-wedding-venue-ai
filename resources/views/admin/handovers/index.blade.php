<x-admin-layout title="Serah terima">
    <div class="mb-4 flex gap-2 text-sm">
        @foreach (['open' => 'Terbuka', 'resolved' => 'Selesai'] as $value => $label)
            <a href="{{ route('admin.handovers.index', ['status' => $value]) }}"
                class="rounded-full px-3 py-1 {{ $status === $value ? 'bg-ink text-white' : 'bg-white border border-stone-300 text-stone-600' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse ($handovers as $handover)
            <a href="{{ route('admin.handovers.show', $handover) }}" class="block rounded-xl border border-stone-200 bg-white p-4 hover:border-stone-300">
                <div class="flex items-center justify-between">
                    <p class="font-medium text-stone-900">{{ \App\Models\HandoverRequest::reasonLabel($handover->reason) }}</p>
                    <p class="text-xs text-stone-400">{{ $handover->created_at->locale('id')->diffForHumans() }}</p>
                </div>
                <p class="mt-1 line-clamp-2 text-sm text-stone-600">{{ $handover->summary }}</p>
            </a>
        @empty
            <p class="rounded-xl border border-stone-200 bg-white px-4 py-8 text-center text-stone-400">Tidak ada data.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $handovers->links() }}</div>
</x-admin-layout>
