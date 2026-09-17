@extends('layouts.app')

@push('head')
<style>
    .shares-shell { width:min(100%,980px); min-height:100vh; margin:auto; padding:28px 18px 60px; }.shares-header { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:28px; }.shares-brand { color:var(--accent); font-weight:800; letter-spacing:-.03em; }.shares-header h1 { margin:4px 0 0; letter-spacing:-.04em; font-size:clamp(25px,4vw,33px); }.shares-header p { margin:5px 0 0; color:var(--muted); }.share-list { display:grid; gap:13px; }.share-card { padding:19px; border:1px solid var(--line); border-radius:14px; background:var(--paper); box-shadow:0 12px 35px #10182812; }.share-card-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }.share-card h2 { margin:0; overflow-wrap:anywhere; font-size:17px; letter-spacing:-.025em; }.share-card h2 a:hover { color:var(--accent); }.share-state { flex:none; border-radius:999px; padding:3px 8px; background:var(--accent-soft); color:var(--accent); font-size:12px; font-weight:700; }.share-state.expired { background:var(--soft-danger); color:var(--danger); }.share-details { margin:4px 0 15px; color:var(--muted); font-size:13px; }.share-url-row { display:flex; gap:7px; }.share-url-row input { min-width:0; padding:8px 9px; font-size:13px; }.share-actions { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:end; gap:15px; margin-top:14px; }.share-duration { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:7px; margin:0; padding:0; border:0; }.share-duration legend { grid-column:1/-1; margin:0 0 1px; font-size:13px; font-weight:650; }.share-duration label { position:relative; margin:0; cursor:pointer; }.share-duration input { position:absolute; opacity:0; pointer-events:none; }.share-duration span { display:block; border:1px solid var(--line); border-radius:8px; padding:7px 6px; color:var(--muted); font-size:12px; text-align:center; }.share-duration input:checked + span { border-color:var(--accent); background:var(--accent-soft); color:var(--accent); font-weight:700; }.share-duration input:focus-visible + span { box-shadow:0 0 0 3px #5e56e91c; }.share-form-footer { display:flex; align-items:center; gap:8px; margin-top:9px; }.revoke-link { color:var(--danger); padding:8px 4px; font-size:13px; font-weight:650; }.revoke-link:hover { text-decoration:underline; }.empty-shares { padding:50px 24px; border:1px dashed var(--line); border-radius:14px; color:var(--muted); text-align:center; }.empty-shares h2 { margin:0 0 7px; color:var(--ink); }.toast { position:fixed; z-index:30; left:50%; bottom:22px; display:flex; align-items:center; gap:12px; min-width:min(360px,calc(100vw - 32px)); max-width:calc(100vw - 32px); padding:12px 14px; border:1px solid var(--line); border-radius:11px; background:var(--paper); color:var(--ink); box-shadow:0 14px 35px #10182835; transform:translateX(-50%); transition:opacity .2s,transform .2s; }.toast.hiding { opacity:0; transform:translate(-50%,12px); }.toast button { margin-left:auto; border:0; background:transparent; color:var(--muted); padding:0 2px; font-size:17px; }.modal { display:none; position:fixed; z-index:15; inset:0; place-items:center; padding:18px; background:#10182880; }.modal:target { display:grid; }.modal-card { width:min(100%,370px); padding:22px; border:1px solid var(--line); border-radius:15px; background:var(--paper); box-shadow:0 20px 60px #11182740; }.modal-card h2 { margin:0 0 8px; font-size:20px; }.modal-card p { margin:0; color:var(--muted); font-size:14px; overflow-wrap:anywhere; }.modal-footer { display:flex; justify-content:flex-end; align-items:center; gap:8px; margin-top:18px; }.modal-footer form { margin:0; }.modal-footer a { padding:9px 12px; color:var(--muted); } @media (max-width:650px) { .shares-header { align-items:flex-start; flex-direction:column; }.share-actions { grid-template-columns:1fr; }.share-duration { grid-template-columns:1fr 1fr; }.share-url-row { flex-wrap:wrap; }.share-url-row input { flex:1 1 100%; } }
</style>
@endpush

@section('body')
<main class="shares-shell">
    <header class="shares-header">
        <div><a class="shares-brand" href="{{ route('notes.index') }}">✦ md-notes</a><h1>{{ __('ui.shared') }}</h1><p>{{ __('ui.manage_shared') }}</p></div>
        <a class="button secondary small" href="{{ route('notes.index') }}">{{ __('ui.back_to_notes') }}</a>
    </header>

    @if (session('status'))<div id="toast" class="toast" role="status"><span>{{ session('status') }}</span><button type="button" aria-label="{{ __('ui.close') }}">×</button></div>@endif

    @if ($shares->isEmpty())
        <section class="empty-shares"><h2>{{ __('ui.no_shared_notes') }}</h2><p>{{ __('ui.shared_context_help') }}</p></section>
    @else
        <section class="share-list" aria-label="{{ __('ui.shared') }}">
            @foreach ($shares as $share)
                @php($isExpired = $share->expires_at?->isPast())
                <article class="share-card">
                    <div class="share-card-header"><div><h2><a href="{{ route('notes.show', ['path' => $share->path]) }}">{{ $share->path }}</a></h2><p class="share-details">@if ($share->expires_at){{ $isExpired ? __('ui.expired_on') : __('ui.expires') }} {{ $share->expires_at->isoFormat('L LT') }}@else {{ __('ui.does_not_expire') }} @endif</p></div><span class="share-state {{ $isExpired ? 'expired' : '' }}">{{ $isExpired ? __('ui.expired') : __('ui.active') }}</span></div>
                    <div class="share-url-row"><input id="share-link-{{ $share->id }}" value="{{ route('shares.show', ['token' => $share->token]) }}" readonly aria-label="{{ __('ui.shared') }}"><button class="button secondary small" type="button" data-copy-share="share-link-{{ $share->id }}">{{ __('ui.copy') }}</button></div>
                    <div class="share-actions">
                        <form method="post" action="{{ route('shares.update', ['share' => $share]) }}">@csrf @method('PATCH')<fieldset class="share-duration"><legend>{{ __('ui.new_duration') }} <span style="font-weight:400;color:var(--muted)">({{ __('ui.from_now') }})</span></legend><label><input type="radio" name="duration" value="1h"><span>{{ __('ui.one_hour') }}</span></label><label><input type="radio" name="duration" value="24h" @checked(!is_null($share->expires_at))><span>{{ __('ui.twenty_four_hours') }}</span></label><label><input type="radio" name="duration" value="7d"><span>{{ __('ui.seven_days') }}</span></label><label><input type="radio" name="duration" value="forever" @checked(is_null($share->expires_at))><span>{{ __('ui.never') }}</span></label></fieldset><div class="share-form-footer"><button class="button small" type="submit">{{ __('ui.save_duration') }}</button></div></form>
                        <a class="revoke-link" href="#revoke-share-{{ $share->id }}">{{ __('ui.revoke_link') }}</a>
                    </div>
                </article>
                <div id="revoke-share-{{ $share->id }}" class="modal"><section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="revoke-title-{{ $share->id }}"><h2 id="revoke-title-{{ $share->id }}">{{ __('ui.revoke_question') }}</h2><p>{{ __('ui.revoke_help', ['path' => $share->path]) }}</p><div class="modal-footer"><a href="#">{{ __('ui.cancel') }}</a><form method="post" action="{{ route('shares.destroy', ['share' => $share]) }}">@csrf @method('DELETE')<button class="button danger" type="submit">{{ __('ui.revoke') }}</button></form></div></section></div>
            @endforeach
        </section>
    @endif
</main>
<script>
    const toast = document.getElementById('toast');
    if (toast) { const dismiss = () => { toast.classList.add('hiding'); setTimeout(() => toast.remove(), 220); }; toast.querySelector('button').onclick = dismiss; setTimeout(dismiss, 3000); }
    const copyText = @json(['copied' => __('ui.copied'), 'copy' => __('ui.copy'), 'selected' => __('ui.selected')]);
    document.querySelectorAll('[data-copy-share]').forEach((button) => button.addEventListener('click', async () => { const input = document.getElementById(button.dataset.copyShare); input.select(); try { await navigator.clipboard.writeText(input.value); button.textContent = copyText.copied; setTimeout(() => button.textContent = copyText.copy, 1500); } catch (_) { button.textContent = copyText.selected; setTimeout(() => button.textContent = copyText.copy, 1500); } }));
</script>
@endsection
