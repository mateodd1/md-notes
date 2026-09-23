@php
    $locale = request()->getPreferredLanguage(['es', 'en']) === 'es' ? 'es' : 'en';
    app()->setLocale($locale);
    $title = __('errors.pages.'.$statusCode.'.title');
    $description = $statusCode === 429 && isset($message)
        ? $message
        : __('errors.pages.'.$statusCode.'.description');
    $homeUrl = config('md-notes.canonical_url') ?: '/';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#f8fafd" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#131314" media="(prefers-color-scheme: dark)">
    <title>{{ $statusCode }} · md-notes</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <script>
        try { const theme = localStorage.getItem('md-notes-theme') || 'system'; if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (_) { if (window.matchMedia?.('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark'); }
    </script>
    <link rel="stylesheet" href="/assets/md-notes-base.css">
    <link rel="stylesheet" href="/assets/md-notes-google.css">
    <link rel="stylesheet" href="/assets/md-notes-errors.css">
</head>
<body>
    <main class="error-shell">
        <section class="error-card">
            <a class="error-brand" href="{{ $homeUrl }}" aria-label="md-notes">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2.4c.7 5.3 3.1 8.1 8.2 9.6-5.1 1.5-7.5 4.3-8.2 9.6-.7-5.3-3.1-8.1-8.2-9.6 5.1-1.5 7.5-4.3 8.2-9.6Z"/></svg>
                md-notes
            </a>
            <p class="error-code">{{ $statusCode }}</p>
            <h1>{{ $title }}</h1>
            <p class="error-description">{{ $description }}</p>
            <div class="error-actions">
                <a class="button" href="{{ $homeUrl }}">{{ __('errors.home') }}</a>
            </div>
        </section>
    </main>
</body>
</html>
