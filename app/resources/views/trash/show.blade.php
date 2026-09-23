@extends('layouts.app')

@php($title = $title.' · '.__('ui.trash').' · md-notes')

@section('body')
<main class="trash-note-shell">
    <header class="trash-note-header">
        <div><a class="trash-note-brand" href="{{ route('notes.index') }}"><x-icon name="spark" class="brand-icon" />md-notes</a><h1>{{ $title }}</h1><p>{{ $item['original_path'] }} · {{ __('ui.deleted_at', ['date' => \Illuminate\Support\Carbon::parse($item['deleted_at'])->isoFormat('L LT')]) }}</p></div>
        <a class="button secondary small" href="{{ route('trash.index') }}">{{ __('ui.back_to_trash') }}</a>
    </header>
    <article class="trash-note-content markdown-body">{!! $rendered !!}</article>
</main>
@endsection
