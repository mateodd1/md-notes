@extends('layouts.app')

@push('head')
<style>
    .version-view-shell { min-height:100vh; padding:26px 18px 60px; }.version-view-header,.version-view-note { width:min(100%,880px); margin:auto; }.version-view-header { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:0 2px 20px; }.version-view-brand { color:var(--accent); font-weight:800; letter-spacing:-.03em; }.version-view-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:7px; }.version-view-note { padding:clamp(22px,5vw,52px); border:1px solid var(--line); border-radius:17px; background:var(--paper); box-shadow:0 18px 50px #10182818; }.version-view-note h1 { margin:0; font-size:clamp(27px,5vw,39px); letter-spacing:-.045em; }.version-view-meta { margin:7px 0 32px; color:var(--muted); font-size:13px; }.version-content { line-height:1.75; }.version-content h2,.version-content h3 { margin-top:1.7em; letter-spacing:-.035em; }.version-content pre { overflow:auto; padding:14px; border-radius:9px; background:var(--preview); }.version-content code { font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }.version-content a { color:var(--accent); text-decoration:underline; } @media (max-width:620px) { .version-view-header { align-items:flex-start; flex-direction:column; }.version-view-actions { justify-content:flex-start; } }
</style>
@endpush

@section('body')
<main class="version-view-shell"><header class="version-view-header"><a class="version-view-brand" href="{{ route('notes.index') }}">✦ md-notes</a><div class="version-view-actions"><a class="button secondary small" href="{{ route('versions.index', ['path' => $version->path]) }}">{{ __('ui.version_history') }}</a><a class="button secondary small" href="{{ route('versions.download', ['version' => $version]) }}">{{ __('ui.download') }}</a></div></header><article class="version-view-note"><h1>{{ $title }}</h1><div class="version-view-meta">{{ $version->path }} · {{ __('ui.version_from') }} {{ $version->created_at->isoFormat('L LTS') }}</div><div class="version-content">{!! $rendered !!}</div></article></main>
@endsection
