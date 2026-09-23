@extends('layouts.app')

@section('body')
    <main class="version-view-shell"><header class="version-view-header"><a class="version-view-brand" href="{{ route('notes.index') }}"><x-icon name="spark" class="brand-icon" />md-notes</a><div class="version-view-actions"><a class="button secondary small" href="{{ route('versions.index', ['path' => $version->path]) }}">{{ __('ui.version_history') }}</a><a class="button secondary small" href="{{ route('versions.download', ['version' => $version]) }}">{{ __('ui.download') }}</a></div></header><article class="version-view-note"><h1>{{ $title }}</h1><div class="version-view-meta">{{ $version->path }} · {{ __('ui.version_from') }} {{ $version->created_at->isoFormat('L LTS') }}</div><div class="version-content markdown-body">{!! $rendered !!}</div></article></main>
@endsection
