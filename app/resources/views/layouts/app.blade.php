<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f8fafd" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#131314" media="(prefers-color-scheme: dark)">
    @include('partials.favicons')
    <title>{{ $title ?? 'md-notes' }}</title>
    @stack('meta')
    <script>
        try { const theme = localStorage.getItem('md-notes-theme') || 'system'; if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (_) { if (window.matchMedia?.('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark'); }
    </script>
    <link rel="stylesheet" href="{{ asset('assets/md-notes-base.css') }}?v={{ filemtime(public_path('assets/md-notes-base.css')) }}">
    @stack('head')
    <link rel="stylesheet" href="{{ asset('assets/md-notes-responsive.css') }}?v={{ filemtime(public_path('assets/md-notes-responsive.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/md-notes-google.css') }}?v={{ filemtime(public_path('assets/md-notes-google.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/md-notes-pages.css') }}?v={{ filemtime(public_path('assets/md-notes-pages.css')) }}">
    <script defer src="{{ asset('assets/md-notes-viewport.js') }}?v={{ filemtime(public_path('assets/md-notes-viewport.js')) }}"></script>
    @if (auth()->check() && auth()->user()->hasVerifiedEmail() && ! auth()->user()->isDemo())
        @php
            $offlineConfig = [
                'account' => \App\Services\OfflineNotes::accountKey(auth()->user()),
                'worker' => route('offline.worker'), 'session' => route('offline.session'),
                'sync' => route('offline.sync'), 'shell' => route('offline.shell'),
                'notes' => route('notes.index'), 'login' => route('login'),
                'locale' => app()->getLocale(), 'translations' => __('offline'),
            ];
        @endphp
        <script>window.mdNotesOfflineConfig = @json($offlineConfig);</script>
        <link rel="stylesheet" href="{{ asset('assets/md-notes-offline.css') }}?v={{ filemtime(public_path('assets/md-notes-offline.css')) }}">
        <script defer src="{{ asset('assets/md-notes-offline-db.js') }}?v={{ filemtime(public_path('assets/md-notes-offline-db.js')) }}"></script>
        <script defer src="{{ asset('assets/md-notes-offline.js') }}?v={{ filemtime(public_path('assets/md-notes-offline.js')) }}"></script>
        <script defer src="{{ asset('assets/vendor/marked.js') }}"></script>
        <script defer src="{{ asset('assets/vendor/purify.js') }}"></script>
    @endif
</head>
<body>
    @yield('body')
</body>
</html>
