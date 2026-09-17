@extends('layouts.app')

@push('head')
<style>
    .versions-shell { width:min(100%,900px); min-height:100vh; margin:auto; padding:28px 18px 60px; }.versions-header { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; margin-bottom:25px; }.versions-brand { color:var(--accent); font-weight:800; letter-spacing:-.03em; }.versions-header h1 { margin:5px 0 3px; font-size:clamp(25px,4vw,33px); letter-spacing:-.04em; }.versions-header p { margin:0; color:var(--muted); overflow-wrap:anywhere; }.version-list { display:grid; gap:11px; }.version-card { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:17px; border:1px solid var(--line); border-radius:13px; background:var(--paper); box-shadow:0 12px 35px #10182812; }.version-card h2 { margin:0 0 2px; font-size:16px; }.version-card p { margin:0; color:var(--muted); font-size:13px; }.version-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:7px; }.version-actions a { display:inline-flex; align-items:center; }.empty-versions { padding:45px 20px; border:1px dashed var(--line); border-radius:14px; color:var(--muted); text-align:center; }.empty-versions h2 { margin:0 0 6px; color:var(--ink); }.modal { display:none; position:fixed; z-index:20; inset:0; place-items:center; padding:18px; background:#10182880; }.modal:target { display:grid; }.modal-card { width:min(100%,390px); padding:22px; border:1px solid var(--line); border-radius:15px; background:var(--paper); box-shadow:0 20px 60px #11182740; }.modal-card h2 { margin:0 0 8px; font-size:20px; }.modal-card p { margin:0; color:var(--muted); font-size:14px; }.modal-footer { display:flex; justify-content:flex-end; align-items:center; gap:8px; margin-top:18px; }.modal-footer form { margin:0; }.modal-footer a { padding:9px 12px; color:var(--muted); } @media (max-width:620px) { .versions-header { flex-direction:column; }.version-card { align-items:flex-start; flex-direction:column; }.version-actions { justify-content:flex-start; } }
</style>
@endpush

@section('body')
<main class="versions-shell">
    <header class="versions-header"><div><a class="versions-brand" href="{{ route('notes.index') }}">✦ md-notes</a><h1>{{ __('ui.version_history') }}</h1><p>{{ $path }}</p></div><a class="button secondary small" href="{{ route('notes.show', ['path' => $path]) }}">{{ __('ui.back_to_note') }}</a></header>

    @if ($versions->isEmpty())
        <section class="empty-versions"><h2>{{ __('ui.no_versions') }}</h2><p>{{ __('ui.version_history_empty_help') }}</p></section>
    @else
        <section class="version-list" aria-label="{{ __('ui.saved_versions') }}">
            @foreach ($versions as $version)
                <article class="version-card"><div><h2>{{ $version->created_at->format('d/m/Y') }} · {{ $version->created_at->format('H:i:s') }}</h2><p>{{ mb_strlen($version->content) }} {{ __('ui.characters') }} · {{ __('ui.saved_at') }} {{ $version->created_at->diffForHumans() }}</p></div><div class="version-actions"><a class="button secondary small" href="{{ route('versions.show', ['version' => $version]) }}">{{ __('ui.view') }}</a><a class="button secondary small" href="{{ route('versions.download', ['version' => $version]) }}">{{ __('ui.download') }}</a><a class="button small" href="#restore-version-{{ $version->id }}">{{ __('ui.restore') }}</a></div></article>
                <div id="restore-version-{{ $version->id }}" class="modal"><section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="restore-title-{{ $version->id }}"><h2 id="restore-title-{{ $version->id }}">{{ __('ui.restore_version_question') }}</h2><p>{{ __('ui.restore_version_specific_help', ['saved_at' => $version->created_at->isoFormat('L LT')]) }}</p><div class="modal-footer"><a href="#">{{ __('ui.cancel') }}</a><form method="post" action="{{ route('versions.restore', ['version' => $version]) }}">@csrf @method('PATCH')<button class="button" type="submit">{{ __('ui.restore') }}</button></form></div></section></div>
            @endforeach
        </section>
    @endif
</main>
@endsection
