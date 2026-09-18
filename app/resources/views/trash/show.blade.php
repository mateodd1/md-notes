@extends('layouts.app')

@php($title = $title.' · '.__('ui.trash').' · md-notes')

@push('head')
<style>
    .trash-note-shell { width:min(100%,920px); min-height:100vh; margin:auto; padding:28px 18px 60px; }.trash-note-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:28px; }.trash-note-brand { color:var(--accent); font-weight:800; letter-spacing:-.03em; }.trash-note-header h1 { margin:4px 0 0; overflow-wrap:anywhere; letter-spacing:-.04em; font-size:clamp(25px,4vw,33px); }.trash-note-header p { margin:5px 0 0; color:var(--muted); font-size:13px; overflow-wrap:anywhere; }.trash-note-content { padding:clamp(22px,5vw,52px); border:1px solid var(--line); border-radius:14px; background:var(--paper); box-shadow:var(--shadow-card); color:var(--ink); line-height:1.7; }.trash-note-content h1,.trash-note-content h2,.trash-note-content h3 { letter-spacing:-.035em; }.trash-note-content pre { overflow:auto; padding:13px; border-radius:8px; background:var(--preview); }.trash-note-content :not(pre) > code { padding:.12em .38em; border-radius:5px; background:var(--preview); font:600 .88em/1.35 ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; }.trash-note-content img { display:block; max-width:100%; height:auto; }.trash-note-content a { color:var(--accent); font-weight:650; text-decoration:underline; text-underline-offset:3px; } @media (max-width:600px) { .trash-note-header { flex-direction:column; } }
</style>
@endpush

@section('body')
<main class="trash-note-shell">
    <header class="trash-note-header">
        <div><a class="trash-note-brand" href="{{ route('notes.index') }}">✦ md-notes</a><h1>{{ $title }}</h1><p>{{ $item['original_path'] }} · {{ __('ui.deleted_at', ['date' => \Illuminate\Support\Carbon::parse($item['deleted_at'])->isoFormat('L LT')]) }}</p></div>
        <a class="button secondary small" href="{{ route('trash.index') }}">{{ __('ui.back_to_trash') }}</a>
    </header>
    <article class="trash-note-content">{!! $rendered !!}</article>
</main>
@endsection
