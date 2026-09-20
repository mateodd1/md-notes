@extends('layouts.app')

@php($title = __('ui.trash').' · md-notes')

@push('head')
<link rel="stylesheet" href="{{ asset('assets/md-notes-trash.css') }}?v={{ filemtime(public_path('assets/md-notes-trash.css')) }}">
<script src="{{ asset('assets/md-notes-trash.js') }}?v={{ filemtime(public_path('assets/md-notes-trash.js')) }}" defer></script>
<style>
    .trash-shell { width:min(100%,920px); min-height:100vh; margin:auto; padding:28px 18px 60px; }.trash-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:28px; }.trash-brand { color:var(--accent); font-weight:800; letter-spacing:-.03em; }.trash-header h1 { margin:4px 0 0; letter-spacing:-.04em; font-size:clamp(25px,4vw,33px); }.trash-header p { max-width:600px; margin:5px 0 0; color:var(--muted); }.trash-list { display:grid; gap:12px; }.trash-card { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:17px 18px; border:1px solid var(--line); border-radius:14px; background:var(--paper); box-shadow:var(--shadow-card); }.trash-card-info { min-width:0; }.trash-card h2 { margin:0; overflow-wrap:anywhere; font-size:16px; letter-spacing:-.02em; }.trash-card p { margin:4px 0 0; color:var(--muted); font-size:13px; }.trash-type { display:inline-block; margin-right:6px; border-radius:999px; padding:2px 7px; background:var(--accent-soft); color:var(--accent); font-size:11px; font-weight:750; text-transform:uppercase; }.trash-actions { display:flex; flex:none; align-items:center; gap:8px; }.empty-trash { padding:50px 24px; border:1px dashed var(--line); border-radius:14px; color:var(--muted); text-align:center; }.empty-trash h2 { margin:0 0 7px; color:var(--ink); }.toast { position:fixed; z-index:30; left:50%; bottom:22px; display:flex; align-items:center; gap:12px; min-width:min(360px,calc(100vw - 32px)); max-width:calc(100vw - 32px); padding:12px 14px; border:1px solid var(--line); border-radius:11px; background:var(--paper); color:var(--ink); box-shadow:0 14px 35px #10182835; transform:translateX(-50%); transition:opacity .2s,transform .2s; }.toast.hiding { opacity:0; transform:translate(-50%,12px); }.toast button { margin-left:auto; border:0; background:transparent; color:var(--muted); padding:0 2px; font-size:17px; }.modal { display:none; position:fixed; z-index:15; inset:0; place-items:center; padding:18px; background:#10182880; }.modal:target { display:grid; }.modal-card { width:min(100%,390px); padding:22px; border:1px solid var(--line); border-radius:15px; background:var(--paper); box-shadow:0 20px 60px #11182740; }.modal-card h2 { margin:0 0 8px; font-size:20px; }.modal-card p { margin:0; color:var(--muted); font-size:14px; overflow-wrap:anywhere; }.modal-footer { display:flex; justify-content:flex-end; align-items:center; gap:8px; margin-top:18px; }.modal-footer form { margin:0; }.modal-footer a { padding:9px 12px; color:var(--muted); } @media (max-width:600px) { .trash-header { flex-direction:column; }.trash-card { align-items:flex-start; flex-direction:column; }.trash-actions { width:100%; }.trash-actions form,.trash-actions .button { flex:1; }.trash-actions .button { width:100%; } }
</style>
@endpush

@section('body')
<main class="trash-shell">
    <header class="trash-header">
        <div><a class="trash-brand" href="{{ route('notes.index') }}">✦ md-notes</a><h1>{{ __('ui.trash') }}</h1><p>{{ __('ui.trash_help') }}</p></div>
        <a class="button secondary small" href="{{ route('notes.index') }}">{{ __('ui.back_to_notes') }}</a>
    </header>

    @if (session('status'))<div id="toast" class="toast" role="status"><span>{{ session('status') }}</span><button type="button" aria-label="{{ __('ui.close') }}">×</button></div>@endif
    @if ($errors->has('trash'))<div class="errors">{{ $errors->first('trash') }}</div>@endif

    @if ($items === [])
        <section class="empty-trash"><h2>{{ __('ui.trash_empty') }}</h2><p>{{ __('ui.trash_help') }}</p></section>
    @else
        <section class="trash-list" aria-label="{{ __('ui.trash') }}">
            @foreach ($items as $item)
                <article class="trash-card">
                    <div class="trash-card-info"><h2>{{ $item['original_path'] }}</h2><p><span class="trash-type">{{ $item['type'] === 'folder' ? __('ui.folder') : 'Markdown' }}</span>{{ __('ui.deleted_at', ['date' => \Illuminate\Support\Carbon::parse($item['deleted_at'])->isoFormat('L LT')]) }}</p></div>
                    <div class="trash-actions">@if ($item['type'] === 'note')<button type="button" class="button secondary small" data-trash-preview-url="{{ route('trash.show', ['id' => $item['id']]) }}" data-trash-preview-title="{{ $item['original_path'] }}" aria-haspopup="dialog" aria-controls="trash-preview">{{ __('ui.view') }}</button>@endif<form method="post" action="{{ route('trash.restore', ['id' => $item['id']]) }}">@csrf<button class="button secondary small" type="submit">{{ __('ui.restore_from_trash') }}</button></form><a class="button danger small" href="#delete-trash-{{ $item['id'] }}">{{ __('ui.delete_permanently') }}</a></div>
                </article>
                <div id="delete-trash-{{ $item['id'] }}" class="modal"><section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="delete-trash-title-{{ $item['id'] }}"><h2 id="delete-trash-title-{{ $item['id'] }}">{{ __('ui.delete_permanently_question') }}</h2><p>{{ __('ui.delete_permanently_help') }}</p><div class="modal-footer"><a href="#">{{ __('ui.cancel') }}</a><form method="post" action="{{ route('trash.destroy', ['id' => $item['id']]) }}">@csrf @method('DELETE')<button class="button danger" type="submit">{{ __('ui.delete_permanently') }}</button></form></div></section></div>
            @endforeach
        </section>
    @endif
</main>
<dialog id="trash-preview" class="trash-preview" aria-labelledby="trash-preview-title" aria-describedby="trash-preview-path" data-loading="{{ __('ui.trash_preview_loading') }}" data-error="{{ __('ui.trash_preview_error') }}">
    <header class="trash-preview-header">
        <div><h2 id="trash-preview-title">{{ __('ui.trash_preview') }}</h2><p id="trash-preview-path"></p></div>
        <button type="button" class="button secondary small" data-trash-preview-close autofocus>{{ __('ui.close') }}</button>
    </header>
    <div class="trash-preview-body">
        <p class="trash-preview-status" role="status" aria-live="polite"></p>
        <button type="button" class="button secondary small" data-trash-preview-retry hidden>{{ __('ui.trash_preview_retry') }}</button>
        <article class="trash-preview-content" hidden></article>
    </div>
</dialog>
<script>
    const toast = document.getElementById('toast');
    if (toast) { const dismiss = () => { toast.classList.add('hiding'); setTimeout(() => toast.remove(), 220); }; toast.querySelector('button').onclick = dismiss; setTimeout(dismiss, 3000); }
</script>
@endsection
