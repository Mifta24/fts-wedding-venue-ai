<x-admin-layout title="Akun saya">
    <section class="max-w-md rounded-xl border border-stone-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-stone-900">Ganti kata sandi</h2>
        <p class="mt-1 text-xs text-stone-500">Masuk sebagai {{ $user->name }} ({{ $user->email }}).</p>
        <form method="POST" action="{{ route('admin.account.password') }}" class="mt-4 space-y-4">
            @csrf @method('PUT')
            <div>
                <label for="current_password" class="block text-sm font-medium text-stone-700">Kata sandi saat ini</label>
                <input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-stone-700">Kata sandi baru</label>
                <input id="password" type="password" name="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-stone-700">Ulangi kata sandi baru</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded-lg bg-ink px-3 py-2 text-sm font-medium text-white hover:bg-gold hover:text-gold-ink">Simpan</button>
        </form>
    </section>
</x-admin-layout>
