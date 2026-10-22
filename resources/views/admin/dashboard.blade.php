@php
    $label = 'font-label text-[11px] uppercase tracking-[.12em] text-stone-500';
@endphp
<x-admin-layout title="Dasbor">
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <a href="{{ route('admin.bookings.index', ['status' => 'pending']) }}" class="rounded-xl border p-4 {{ $stats['waiting_bookings'] > 0 ? 'border-amber-300 bg-amber-50' : 'border-stone-200 bg-white' }}">
            <p class="{{ $label }}">Permintaan menunggu</p>
            <p class="mt-2 text-3xl font-semibold text-stone-900">{{ $stats['pending_bookings'] }}</p>
            <p class="mt-1 text-xs {{ $stats['waiting_bookings'] > 0 ? 'font-medium text-amber-700' : 'text-stone-400' }}">
                {{ $stats['waiting_bookings'] > 0 ? $stats['waiting_bookings'].' menunggu lebih dari '.$waitingHours.' jam' : 'Tidak ada yang menunggu lebih dari '.$waitingHours.' jam' }}
            </p>
        </a>
        <a href="{{ route('admin.handovers.index') }}" class="rounded-xl border p-4 {{ $stats['open_handovers'] > 0 ? 'border-amber-300 bg-amber-50' : 'border-stone-200 bg-white' }}">
            <p class="{{ $label }}">Serah terima terbuka</p>
            <p class="mt-2 text-3xl font-semibold text-stone-900">{{ $stats['open_handovers'] }}</p>
            <p class="mt-1 text-xs {{ $stats['open_handovers'] > 0 ? 'font-medium text-amber-700' : 'text-stone-400' }}">{{ $stats['open_handovers'] > 0 ? 'Ada pasangan yang menunggu tim' : 'Semua sudah dijawab' }}</p>
        </a>
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <p class="{{ $label }}">Pernikahan · {{ $upcomingDays }} hari</p>
            <p class="mt-2 text-3xl font-semibold text-stone-900">{{ $stats['upcoming_events'] }}</p>
            <p class="mt-1 text-xs text-stone-400">Reservasi terkonfirmasi</p>
        </div>
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <p class="{{ $label }}">Tanggal terisi · {{ $bookedWindowDays }} hari</p>
            <p class="mt-2 text-3xl font-semibold text-stone-900">{{ $stats['booked_percent'] === null ? '—' : $stats['booked_percent'].'%' }}</p>
            @if ($stats['booked_percent'] === null)
                <p class="mt-1 text-xs text-stone-400">Belum ada tanggal dibuka</p>
            @else
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full bg-ink" style="width: {{ $stats['booked_percent'] }}%"></div></div>
            @endif
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-stone-200 bg-white">
            <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
                <p class="font-medium text-stone-900">Serah terima terbuka</p>
                <a href="{{ route('admin.handovers.index') }}" class="py-1 text-xs text-stone-500 hover:underline">Lihat semua</a>
            </div>
            <div class="divide-y divide-stone-100">
                @forelse ($openHandovers as $handover)
                    <a href="{{ route('admin.handovers.show', $handover) }}" class="block px-4 py-3 hover:bg-stone-50">
                        <p class="text-sm font-medium text-stone-900">{{ \App\Models\HandoverRequest::reasonLabel($handover->reason) }}</p>
                        <p class="mt-0.5 line-clamp-1 text-xs text-stone-500">{{ $handover->summary }}</p>
                    </a>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-stone-400">Tidak ada serah terima terbuka.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-stone-200 bg-white">
            <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
                <p class="font-medium text-stone-900">Pernikahan mendatang</p>
                <a href="{{ route('admin.bookings.index', ['status' => 'confirmed']) }}" class="py-1 text-xs text-stone-500 hover:underline">Lihat terkonfirmasi</a>
            </div>
            <div class="divide-y divide-stone-100">
                @forelse ($upcoming as $booking)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-stone-900">{{ $booking->client_name }}</p>
                            <p class="mt-0.5 truncate text-xs text-stone-500">{{ $booking->hall->name }} · {{ $booking->guest_count }} tamu</p>
                        </div>
                        <p class="shrink-0 font-label text-xs text-stone-600">{{ $booking->event_date->locale('id')->translatedFormat('D, j M Y') }}</p>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-stone-400">Tidak ada pernikahan terkonfirmasi dalam {{ $upcomingDays }} hari ke depan.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-stone-200 bg-white lg:col-span-2">
            <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
                <p class="font-medium text-stone-900">Permintaan tanggal terbaru</p>
                <a href="{{ route('admin.bookings.index') }}" class="py-1 text-xs text-stone-500 hover:underline">Lihat semua</a>
            </div>
            <div class="divide-y divide-stone-100">
                @forelse ($recentBookings as $booking)
                    <div class="px-4 py-3">
                        <p class="text-sm font-medium text-stone-900">{{ $booking->client_name }} — {{ $booking->hall->name }}</p>
                        <p class="mt-0.5 text-xs text-stone-500">{{ $booking->event_date->locale('id')->translatedFormat('D, j M Y') }} · {{ \App\Models\Booking::statusLabel($booking->status) }}</p>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-stone-400">Belum ada reservasi.</p>
                @endforelse
            </div>
        </div>
    </div>

    <p class="mt-6 font-label text-[11px] uppercase tracking-[.12em] text-stone-400">
        {{ $stats['halls'] }} hall · {{ $stats['knowledge_items'] }} entri pengetahuan
    </p>
</x-admin-layout>
