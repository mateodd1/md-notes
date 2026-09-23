@extends('layouts.app')

@push('head')
<script defer src="{{ asset('assets/md-notes-register.js') }}?v={{ filemtime(public_path('assets/md-notes-register.js')) }}"></script>
@endpush

@section('body')
<main class="auth-shell auth-split-layout">
    <aside class="auth-intro">
        <span class="auth-intro-label">{{ __('ui.register_intro_label') }}</span>
        <h2>{{ __('ui.register_intro_title') }}</h2>
        <p>{{ __('ui.register_intro_copy') }}</p>
        <div class="auth-intro-items">
            <p><x-icon name="file" />{{ __('ui.register_intro_notes') }}</p>
            <p><x-icon name="folder" />{{ __('ui.register_intro_folders') }}</p>
            <p><x-icon name="external-link" />{{ __('ui.register_intro_sharing') }}</p>
        </div>
        <a href="{{ route('documentation') }}">{{ __('ui.login_intro_docs') }} →</a>
    </aside>
    <section class="auth-card">
        <div class="auth-brand"><x-icon name="spark" class="brand-icon" />md-notes</div>
        <h1>{{ __('ui.create_your_account') }}</h1>
        <p>{{ __('ui.private_space') }}</p>
        <form id="register-form" method="post" action="{{ route('register.store') }}" autocomplete="off" data-close-label="{{ __('ui.close') }}" data-invalid-message="{{ __('ui.register_invalid_field') }}" novalidate>
            @csrf
            <label for="name">{{ __('ui.name') }}</label><input id="name" name="name" value="{{ is_string(old('name')) ? old('name') : '' }}" minlength="3" maxlength="80" autocomplete="off" data-lpignore="true" data-1p-ignore="true" @error('name') aria-invalid="true" @enderror required autofocus>
            <label for="email">{{ __('ui.email') }}</label><input id="email" name="email" type="email" value="{{ is_string(old('email')) ? old('email') : '' }}" autocomplete="off" autocapitalize="none" spellcheck="false" data-lpignore="true" data-1p-ignore="true" @error('email') aria-invalid="true" @enderror required>
            <label for="password">{{ __('ui.password') }} <span class="label-help">({{ __('ui.minimum_8') }})</span></label><input id="password" name="password" type="password" minlength="8" maxlength="128" autocomplete="new-password" @error('password') aria-invalid="true" @enderror required>
            <label for="password_confirmation">{{ __('ui.confirm_password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" maxlength="128" autocomplete="new-password" @error('password_confirmation') aria-invalid="true" @enderror required>
            <button class="button" type="submit">{{ __('ui.sign_up') }}</button>
        </form>
        <p class="form-foot">{{ __('ui.already_account') }} <a href="{{ route('login') }}">{{ __('ui.login') }}</a></p>
    </section>
</main>
@if ($errors->any())
<div id="registration-toast" class="toast error auth-registration-toast is-entering" role="alert" aria-live="assertive">
    <div class="auth-toast-copy">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    <button type="button" aria-label="{{ __('ui.close') }}"><x-icon name="close" class="close-icon" /></button>
</div>
@endif
@endsection
