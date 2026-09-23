@extends('layouts.app')

@section('body')
<main class="versions-shell">
    <header class="versions-header"><div><a class="versions-brand" href="{{ route('notes.index') }}"><x-icon name="spark" class="brand-icon" />md-notes</a><h1>{{ __('ui.version_history') }}</h1><p>{{ $path }}</p></div><a class="button secondary small" href="{{ route('notes.show', ['path' => $path]) }}">{{ __('ui.back_to_note') }}</a></header>

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
