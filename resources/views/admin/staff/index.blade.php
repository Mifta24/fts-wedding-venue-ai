<x-admin-layout title="Tim">
    <p class="mb-4 text-sm text-stone-500">Orang yang bisa masuk ke panel admin gedung ini. Pemilik bisa mengelola tim dan pengaturan; staf mengelola hall, reservasi, dan serah terima.</p>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Peran</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($members as $member)
                    @php($self = $member->is(auth()->user()))
                    <tr>
                        <td class="px-4 py-3"><span class="font-medium text-stone-900">{{ $member->name }}</span>@if ($self) <span class="text-xs text-stone-400">(Anda)</span>@endif<br><span class="text-xs text-stone-500">{{ $member->email }}</span></td>
                        @if ($self)
                            <td class="px-4 py-3 text-stone-600">{{ $member->pivot->role === 'owner' ? 'Pemilik' : 'Staf' }}</td>
                            <td class="px-4 py-3 text-stone-600">Aktif</td>
                            <td class="px-4 py-3"></td>
                        @else
                            <td class="px-4 py-3" colspan="2">
                                <form method="POST" action="{{ route('admin.staff.update', $member) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf @method('PUT')
                                    <select name="role" aria-label="Peran {{ $member->name }}" class="rounded-lg border border-stone-300 px-2 py-1 text-sm">
                                        <option value="staff" @selected($member->pivot->role === 'staff')>Staf</option>
                                        <option value="owner" @selected($member->pivot->role === 'owner')>Pemilik</option>
                                    </select>
                                    <select name="status" aria-label="Status {{ $member->name }}" class="rounded-lg border border-stone-300 px-2 py-1 text-sm">
                                        <option value="active" @selected($member->pivot->status === 'active')>Aktif</option>
                                        <option value="inactive" @selected($member->pivot->status !== 'active')>Nonaktif</option>
                                    </select>
                                    <button type="submit" class="text-stone-600 hover:underline">Simpan</button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('admin.staff.destroy', $member) }}" class="inline" onsubmit="return confirm('Keluarkan {{ $member->name }} dari tim?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Keluarkan</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <section class="mt-8 max-w-xl rounded-xl border border-stone-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-stone-900">Tambah anggota</h2>
        <form method="POST" action="{{ route('admin.staff.store') }}" class="mt-4 grid grid-cols-2 gap-4">
            @csrf
            <div class="col-span-2 sm:col-span-1">
                <label for="name" class="block text-sm font-medium text-stone-700">Nama</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <div class="col-span-2 sm:col-span-1">
                <label for="email" class="block text-sm font-medium text-stone-700">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <div class="col-span-2 sm:col-span-1">
                <label for="password" class="block text-sm font-medium text-stone-700">Kata sandi awal</label>
                <input id="password" type="password" name="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-stone-500">Minimal 8 karakter. Mereka bisa menggantinya di menu Akun saya.</p>
            </div>
            <div class="col-span-2 sm:col-span-1">
                <label for="role" class="block text-sm font-medium text-stone-700">Peran</label>
                <select id="role" name="role" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    <option value="staff">Staf</option>
                    <option value="owner">Pemilik</option>
                </select>
            </div>
            <div class="col-span-2">
                <button type="submit" class="rounded-lg bg-ink px-3 py-2 text-sm font-medium text-white hover:bg-gold hover:text-gold-ink">Tambah</button>
            </div>
        </form>
    </section>
</x-admin-layout>
