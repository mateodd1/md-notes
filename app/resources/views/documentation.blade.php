@extends('layouts.app')

@php
    $es = app()->getLocale() === 'es';
    $canonical = route('documentation');
    $description = $es
        ? 'Documentación de md-notes: aprende a organizar, editar, compartir y subir notas Markdown desde el navegador o la terminal.'
        : 'md-notes documentation: learn how to organise, edit, share, and upload Markdown notes from your browser or terminal.';
    $socialImage = asset('android-chrome-512x512.png');
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'TechArticle',
        '@id' => $canonical.'#documentation',
        'headline' => $es ? 'Documentación de md-notes' : 'md-notes documentation',
        'description' => $description,
        'url' => $canonical,
        'mainEntityOfPage' => $canonical,
        'dateModified' => $modifiedAt,
        'inLanguage' => $es ? 'es' : 'en',
        'author' => [
            '@type' => 'Organization',
            'name' => 'md-notes',
            'url' => route('home'),
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'md-notes',
            'url' => route('home'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $socialImage,
            ],
        ],
    ];
@endphp

@push('meta')
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">
<meta property="og:type" content="article">
<meta property="og:site_name" content="md-notes">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ $es ? 'es_ES' : 'en_US' }}">
<meta property="og:image" content="{{ $socialImage }}">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="512">
<meta property="og:image:height" content="512">
<meta property="og:image:alt" content="md-notes">
<meta property="article:modified_time" content="{{ $modifiedAt }}">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $socialImage }}">
<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endpush

@push('head')
<style>
    .docs-shell { width:min(1160px,calc(100% - 64px)); min-height:100vh; margin:auto; padding:28px 0 70px; }.docs-header { display:flex; align-items:center; justify-content:space-between; gap:16px; padding-bottom:24px; border-bottom:1px solid var(--line); }.docs-brand { color:var(--accent); font-weight:800; letter-spacing:-.03em; }.docs-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:9px; }.docs-content { width:100%; margin:42px auto 0; }.docs-content > :first-child { margin-top:0; }.docs-content h1,.docs-content h2,.docs-content h3 { letter-spacing:-.045em; }.docs-content h1 { margin-bottom:14px; font-size:clamp(32px,5vw,48px); line-height:1; }.docs-content h2 { margin-top:42px; padding-top:9px; border-top:1px solid var(--line); font-size:25px; }.docs-content h3 { margin-top:28px; }.docs-content p,.docs-content li { color:var(--muted); }.docs-content strong { color:var(--ink); }.docs-content a { color:var(--accent); font-weight:650; text-decoration:underline; text-underline-offset:3px; }.docs-content code { padding:.15em .4em; border-radius:5px; background:var(--preview); color:var(--ink); font:600 .88em ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; }.docs-content pre { overflow:auto; padding:15px; border-radius:11px; background:var(--preview); border:1px solid var(--line); }.docs-content pre code { padding:0; background:transparent; }.docs-content li + li { margin-top:7px; } @media (max-width:560px) { .docs-header { gap:10px; }.docs-actions { margin-left:auto; gap:6px; }.docs-content { margin-top:30px; } }
    @media (max-width:780px) { .docs-shell { width:calc(100% - 40px); } }
    @media (max-width:480px) { .docs-shell { width:calc(100% - 32px); } }
</style>
@endpush

@section('body')
<main class="docs-shell">
    <header class="docs-header"><a class="docs-brand" href="{{ route('home') }}">✦ md-notes</a><div class="docs-actions">@auth<a class="button secondary small" href="{{ route('notes.index') }}">{{ __('ui.back_to_notes') }}</a>@else<a class="button secondary small" href="{{ route('login') }}">{{ __('ui.login') }}</a><a class="button small" href="{{ route('register') }}">{{ __('ui.sign_up') }}</a>@endauth</div></header>
    <article class="docs-content">{!! $rendered !!}</article>
</main>
@endsection
