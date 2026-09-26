@extends('layouts.app')

@push('meta')
<meta name="description" content="{{ __('ui.shared_note') }}">
<link rel="canonical" href="{{ $canonicalUrl }}">
<meta property="og:type" content="article">
<meta property="og:site_name" content="md-notes">
<meta property="og:title" content="{{ $title }} · md-notes">
<meta property="og:description" content="{{ __('ui.shared_note') }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:locale" content="{{ app()->getLocale() === 'es' ? 'es_ES' : 'en_US' }}">
<meta property="og:image" content="{{ asset('android-chrome-512x512.png') }}">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $title }} · md-notes">
<meta name="twitter:description" content="{{ __('ui.shared_note') }}">
<meta name="twitter:image" content="{{ asset('android-chrome-512x512.png') }}">
@endpush

@section('body')
<main class="shared-shell">
    @if ($errors->any())
        <div class="errors" role="alert">{{ $errors->first() }}</div>
    @endif
    <header class="shared-header"><span class="shared-brand"><x-icon name="spark" class="brand-icon" />md-notes</span><div class="shared-header-right"><span class="shared-label">{{ __('ui.shared_note') }}</span>@auth @if (auth()->id() !== $share->user_id)<form method="post" action="{{ route('shares.copy', ['token' => $share->token]) }}">@csrf<button class="shared-copy" type="submit"><x-icon name="copy-plus" class="button-icon" />{{ __('ui.copy_to_your_space') }}</button></form>@endif @endauth<details class="shared-theme"><summary><x-icon name="sun" class="theme-menu-icon" />{{ __('ui.theme') }}</summary><div class="shared-theme-menu"><button type="button" data-share-theme="system"><x-icon name="monitor" class="theme-menu-icon" />{{ __('ui.system_theme') }}</button><button type="button" data-share-theme="light"><x-icon name="sun" class="theme-menu-icon" />{{ __('ui.light_mode') }}</button><button type="button" data-share-theme="dark"><x-icon name="moon" class="theme-menu-icon" />{{ __('ui.dark_mode') }}</button></div></details></div></header>
    <article class="shared-note">@if ($showFileTitle)<h1>{{ $title }}</h1>@endif<div class="shared-content markdown-body">{!! $rendered !!}</div>@if ($expirationLabel)<footer class="shared-note-expiration"><time datetime="{{ $share->expires_at->toIso8601String() }}">{{ $expirationLabel }}</time></footer>@endif</article>
</main>
<script>
    const enhanceImages = (root) => { root.querySelectorAll('img').forEach((image) => { if (image.closest('.note-image')) return; const imageElement = image.closest('a') || image; const wrapper = document.createElement('span'); wrapper.className = 'note-image'; imageElement.parentNode.insertBefore(wrapper, imageElement); wrapper.append(imageElement); const download = document.createElement('a'); download.className = 'image-download'; download.href = image.currentSrc || image.src; download.download = ''; download.title = @json(__('ui.download_image')); download.setAttribute('aria-label', @json(__('ui.download_image'))); download.innerHTML = '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M4 20h16"/></svg>'; wrapper.append(download); }); };
    enhanceImages(document.querySelector('.shared-content'));
    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
    const resolveTheme = (theme) => theme === 'system' ? (systemTheme.matches ? 'dark' : 'light') : theme;
    const setShareTheme = (theme, persist = true) => {
        document.documentElement.classList.toggle('dark', resolveTheme(theme) === 'dark');
        if (persist) try { localStorage.setItem('md-notes-theme', theme); } catch (_) {}
        document.querySelectorAll('[data-share-theme]').forEach((button) => button.classList.toggle('selected', button.dataset.shareTheme === theme));
    };
    let savedTheme = 'system';
    try { savedTheme = localStorage.getItem('md-notes-theme') || 'system'; } catch (_) {}
    setShareTheme(savedTheme, false);
    document.querySelectorAll('[data-share-theme]').forEach((button) => button.addEventListener('click', () => setShareTheme(button.dataset.shareTheme)));
    systemTheme.addEventListener('change', () => { let selected = 'system'; try { selected = localStorage.getItem('md-notes-theme') || 'system'; } catch (_) {} if (selected === 'system') setShareTheme('system', false); });
</script>
@endsection
