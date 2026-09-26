<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>md-notes · {{ __('offline.title') }}</title>
    <link rel="stylesheet" href="/assets/md-notes-base.css">
    <link rel="stylesheet" href="/assets/md-notes-google.css">
    <link rel="stylesheet" href="/assets/md-notes-offline.css">
    <script>window.mdNotesOfflinePage = @json(['translations' => __('offline'), 'notes' => route('notes.index'), 'login' => route('login')]);</script>
    <script defer src="/assets/vendor/marked.js"></script>
    <script defer src="/assets/vendor/purify.js"></script>
    <script defer src="/assets/md-notes-offline-db.js"></script>
    <script defer src="/assets/md-notes-offline.js"></script>
    <script defer src="/assets/md-notes-offline-page.js"></script>
    <script defer src="/assets/md-notes-viewport.js"></script>
</head>
<body class="offline-page">
    <aside class="offline-sidebar">
        <a class="offline-brand" href="{{ route('notes.index') }}"><x-icon name="spark" />md-notes</a>
        <h2>{{ __('offline.cached') }}</h2>
        <input id="offline-search" type="search" placeholder="{{ __('offline.search') }}" aria-label="{{ __('offline.search') }}" autocomplete="off">
        <nav id="offline-list" class="offline-list" aria-label="{{ __('offline.title') }}"></nav>
        <p class="offline-help">{{ __('offline.help') }}</p>
        <a class="button secondary" href="{{ route('notes.index') }}">{{ __('offline.back') }}</a>
    </aside>
    <main class="offline-main">
        <header class="offline-header">
            <button id="offline-show-notes" class="button secondary offline-mobile-toggle" type="button">{{ __('ui.notes') }}</button>
            <h1 id="offline-title">{{ __('offline.title') }}</h1>
            <div class="offline-actions">
                <button id="offline-theme" class="button secondary" type="button"><x-icon name="sun" />{{ __('offline.theme') }}</button>
                <button id="offline-sync" class="button secondary" type="button">{{ __('offline.sync') }}</button>
                <button id="offline-edit" class="button secondary" type="button" hidden>{{ __('offline.edit') }}</button>
                <button id="offline-save" class="button" type="button" hidden>{{ __('offline.save') }}</button>
                <button id="offline-download" class="button secondary" type="button" hidden>{{ __('offline.download') }}</button>
            </div>
        </header>
        <div id="offline-message" class="offline-message" role="status" aria-live="polite"></div>
        <div class="offline-body">
            <article id="offline-reader" class="offline-reader markdown-body"><p>{{ __('offline.empty') }}</p></article>
            <textarea id="offline-editor" class="offline-editor" aria-label="{{ __('ui.markdown_content') }}" spellcheck="true" hidden></textarea>
        </div>
    </main>
</body>
</html>
