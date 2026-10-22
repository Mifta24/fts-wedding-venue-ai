@php
    $currentVenue = auth()->user()?->currentVenue();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} — {{ $currentVenue?->name ?? config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ivory font-sans text-stone-900 antialiased">
    <div class="min-h-screen lg:flex">
        <header class="sticky top-0 z-20 bg-ink text-white lg:hidden">
            <details class="group">
                <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 [&::-webkit-details-marker]:hidden">
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold">{{ $currentVenue?->name }}</span>
                        <span class="block truncate font-label text-[11px] uppercase tracking-[.14em] text-white/70">Admin · {{ $title ?? 'Dashboard' }}</span>
                    </span>
                    <span class="ml-3 grid size-10 shrink-0 place-items-center rounded border border-white/25 group-open:bg-white/10" aria-label="Menu">
                        <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M3 5.5h14M3 10h14M3 14.5h14"/></svg>
                    </span>
                </summary>
                <div class="border-t border-white/10"><x-admin-nav /></div>
            </details>
        </header>

        <aside class="hidden w-56 shrink-0 flex-col bg-ink text-white lg:sticky lg:top-0 lg:flex lg:h-screen">
            <div class="border-b border-white/10 px-4 py-4">
                <p class="font-serif text-lg font-semibold leading-tight">{{ $currentVenue?->name }}</p>
                <p class="mt-0.5 font-label text-[11px] uppercase tracking-[.14em] text-gold">Admin</p>
            </div>
            <div class="flex flex-1 flex-col justify-between overflow-y-auto"><x-admin-nav /></div>
        </aside>

        <main class="min-w-0 flex-1">
            <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8">
                @if (session('status'))
                    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <p class="font-medium">Please fix the following:</p>
                        <ul class="mt-1 list-inside list-disc">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h1 class="font-serif text-3xl font-medium text-stone-900">{{ $title ?? 'Dashboard' }}</h1>
                    {{ $actions ?? '' }}
                </div>

                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
