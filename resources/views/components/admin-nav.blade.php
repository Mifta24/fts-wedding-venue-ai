@php
    $isOwner = auth()->user()?->currentVenue()?->pivot?->role === 'owner';

    $navItems = [
        ['route' => 'admin.dashboard', 'label' => 'Dasbor'],
        ['route' => 'admin.halls.index', 'label' => 'Hall'],
        ['route' => 'admin.knowledge-items.index', 'label' => 'Basis pengetahuan'],
        ['route' => 'admin.bookings.index', 'label' => 'Reservasi'],
        ['route' => 'admin.handovers.index', 'label' => 'Serah terima'],
    ];

    if ($isOwner) {
        $navItems[] = ['route' => 'admin.staff.index', 'label' => 'Tim'];
        $navItems[] = ['route' => 'admin.activity.index', 'label' => 'Log aktivitas'];
        $navItems[] = ['route' => 'admin.settings.edit', 'label' => 'Pengaturan'];
    }

    $navItems[] = ['route' => 'admin.account.edit', 'label' => 'Akun saya'];
@endphp
<nav class="space-y-1 px-2 py-4 text-sm" aria-label="Admin">
    @foreach ($navItems as $item)
        <a
            href="{{ route($item['route']) }}"
            @if (request()->routeIs($item['route'].'*')) aria-current="page" @endif
            class="block rounded-lg px-3 py-2.5 {{ request()->routeIs($item['route'].'*') ? 'bg-gold font-medium text-gold-ink' : 'text-white/70 hover:bg-white/10 hover:text-white' }}"
        >{{ $item['label'] }}</a>
    @endforeach
</nav>
<form method="POST" action="{{ route('admin.logout') }}" class="border-t border-white/10 p-2">
    @csrf
    <button type="submit" class="w-full rounded-lg px-3 py-2.5 text-left text-sm text-white/60 hover:bg-white/10 hover:text-white">Keluar</button>
</form>
