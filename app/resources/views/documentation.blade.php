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

@section('body')
<main class="docs-shell">
    <header class="docs-header"><a class="docs-brand" href="{{ route('home') }}"><x-icon name="spark" class="brand-icon" />md-notes</a><div class="docs-actions">@auth<a class="button secondary small" href="{{ route('notes.index') }}">{{ __('ui.back_to_notes') }}</a>@else<a class="button secondary small" href="{{ route('login') }}">{{ __('ui.login') }}</a><a class="button small" href="{{ route('register') }}">{{ __('ui.sign_up') }}</a>@endauth</div></header>
    <article class="docs-content markdown-body">{!! $rendered !!}</article>
</main>
@endsection
