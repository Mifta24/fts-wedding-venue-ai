<!DOCTYPE html>
<html lang="{{ $locale === 'zh' ? 'zh-TW' : $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $copy['title'] }} · {{ $venue->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="access-page {{ $venue->theme ? 'theme-'.$venue->theme : '' }}">
    <main class="access-card">
        <p class="access-brand">{{ $venue->name }}</p>
        <h1>{{ $copy['title'] }}</h1>
        <p class="access-lead">{{ $copy['lead'] }}</p>
        <form method="POST" action="{{ route('venue.access.store', ['venueSlug' => $venue->slug]) }}">
            @csrf
            <input type="hidden" name="lang" value="{{ $locale }}">
            <label for="code">{{ $copy['label'] }}</label>
            <input id="code" type="password" name="code" autocomplete="off" autofocus required maxlength="100">
            @error('code')<p class="access-error" role="alert">{{ $message }}</p>@enderror
            <button type="submit">{{ $copy['button'] }}</button>
        </form>
        <nav class="access-lang" aria-label="Language">
            @foreach ($supportedLocales as $code)
                <a href="?lang={{ $code }}" lang="{{ $code }}" @if($code === $locale) aria-current="true" @endif>{{ strtoupper($code) }}</a>
            @endforeach
        </nav>
    </main>
</body>
</html>
