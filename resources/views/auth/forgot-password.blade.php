<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lupa kata sandi · Wedding Venue AI</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-ivory font-sans">
    <div class="w-full max-w-sm rounded-2xl border border-gold/40 bg-white p-7 shadow-sm">
        <h1 class="font-serif text-3xl font-medium text-stone-900">Lupa kata sandi</h1>
        <p class="mt-1 text-sm text-stone-500">Masukkan email akun admin Anda. Kami kirimkan tautan untuk mengatur ulang kata sandi.</p>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.password.email') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-stone-700" for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-stone-500 focus:outline-none">
            </div>
            <button type="submit" class="w-full rounded-lg bg-ink px-3 py-2 text-sm font-medium text-white hover:bg-gold hover:text-gold-ink">Kirim tautan</button>
            <p class="text-center text-sm"><a href="{{ route('admin.login') }}" class="text-stone-500 hover:underline">Kembali ke halaman masuk</a></p>
        </form>
    </div>
</body>
</html>
