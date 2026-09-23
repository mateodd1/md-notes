@extends('layouts.app')

@section('body')
<main class="shares-shell">
    <header class="shares-header">
        <div><a class="shares-brand" href="{{ route('notes.index') }}"><x-icon name="spark" class="brand-icon" />md-notes</a><h1>{{ __('ui.shared') }}</h1><p>{{ __('ui.manage_shared') }}</p></div>
        <a class="button secondary small" href="{{ route('notes.index') }}">{{ __('ui.back_to_notes') }}</a>
    </header>

    @if (session('status'))<div id="toast" class="toast" role="status"><span>{{ session('status') }}</span><button type="button" aria-label="{{ __('ui.close') }}"><x-icon name="close" class="close-icon" /></button></div>@endif

    @if ($shares->isEmpty())
        <section class="empty-shares"><h2>{{ __('ui.no_shared_notes') }}</h2><p>{{ __('ui.shared_context_help') }}</p></section>
    @else
        @if ($activeShares->isNotEmpty())
            <section class="share-list" aria-label="{{ __('ui.active_links') }}">
                @foreach ($activeShares as $share)
                    @include('shares._card', ['share' => $share, 'isExpired' => false])
                @endforeach
            </section>
        @endif
        @if ($expiredShares->isNotEmpty())
            <details class="expired-shares">
                <summary><span class="expired-shares-title"><strong>{{ __('ui.expired_links') }}</strong><small>{{ __('ui.expired_links_help') }}</small></span><span class="expired-shares-count" aria-label="{{ trans_choice('ui.expired_links_count', $expiredShares->count(), ['count' => $expiredShares->count()]) }}">{{ $expiredShares->count() }}</span><svg class="expired-shares-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                <section class="share-list expired-share-list" aria-label="{{ __('ui.expired_links') }}">
                    @foreach ($expiredShares as $share)
                        @include('shares._card', ['share' => $share, 'isExpired' => true])
                    @endforeach
                </section>
            </details>
        @endif
    @endif
</main>
<script>
    const toast = document.getElementById('toast');
    if (toast) { const dismiss = () => { toast.classList.add('hiding'); setTimeout(() => toast.remove(), 220); }; toast.querySelector('button').onclick = dismiss; setTimeout(dismiss, 3000); }
    const copyText = @json(['copied' => __('ui.copied'), 'copy' => __('ui.copy'), 'selected' => __('ui.selected')]);
    document.querySelectorAll('[data-copy-share]').forEach((button) => button.addEventListener('click', async () => { const input = document.getElementById(button.dataset.copyShare); input.select(); try { await navigator.clipboard.writeText(input.value); button.textContent = copyText.copied; setTimeout(() => button.textContent = copyText.copy, 1500); } catch (_) { button.textContent = copyText.selected; setTimeout(() => button.textContent = copyText.copy, 1500); } }));
</script>
@endsection
