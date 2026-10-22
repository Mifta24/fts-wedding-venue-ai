<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atur ulang kata sandi · Wedding Venue AI</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-ivory font-sans">
    <div class="w-full max-w-sm rounded-2xl border border-gold/40 bg-white p-7 shadow-sm">
        <h1 class="font-serif text-3xl font-medium text-stone-900">Kata sandi baru</h1>
        <p class="mt-1 text-sm text-stone-500">Pilih kata sandi baru, minimal 8 karakter.</p>

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.password.update') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label class="block text-sm font-medium text-stone-700" for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-stone-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700" for="password">Kata sandi baru</label>
                <input id="password" type="password" name="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-stone-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700" for="password_confirmation">Ulangi kata sandi baru</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-stone-500 focus:outline-none">
            </div>
            <button type="submit" class="w-full rounded-lg bg-ink px-3 py-2 text-sm font-medium text-white hover:bg-gold hover:text-gold-ink">Simpan kata sandi</button>
        </form>
    </div>
</body>
</html>
