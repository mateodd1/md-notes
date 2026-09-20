@extends('layouts.app')

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
