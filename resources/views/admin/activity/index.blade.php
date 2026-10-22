<x-admin-layout title="Log aktivitas">
    <p class="mb-4 text-sm text-stone-500">Siapa mengubah apa di panel admin ini.</p>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">Waktu</th>
                    <th class="px-4 py-3">Oleh</th>
                    <th class="px-4 py-3">Aktivitas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-stone-500">{{ $log->created_at->timezone($venue->timezone)->locale('id')->translatedFormat('j M Y H:i') }}</td>
                        <td class="px-4 py-3 text-stone-900">{{ $log->user_name ?? 'Sistem' }}</td>
                        <td class="px-4 py-3 text-stone-700">{{ $log->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-stone-400">Belum ada aktivitas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-admin-layout>
