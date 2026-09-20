@extends('layouts.app')

@section('body')
<main class="auth-shell">
    <section class="auth-card" role="alert">
        <h1>md-notes</h1>
        <p>{{ $message }}</p>
        <a class="button" href="{{ auth()->check() ? route('notes.index') : route('login') }}">{{ auth()->check() ? __('ui.back_to_notes') : __('ui.login') }}</a>
    </section>
</main>
@endsection
