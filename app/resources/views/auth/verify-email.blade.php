@extends('layouts.app')

@section('body')
<main class="auth-shell">
    <section class="auth-card auth-verify-card">
        <div class="auth-brand"><x-icon name="spark" class="brand-icon" />md-notes</div>
        <h1>{{ __('ui.verify_account_title') }}</h1>
        <p>{{ __('ui.verify_account_help', ['email' => auth()->user()->email]) }}</p>
        @if (session('status')) <p class="auth-notice" role="status">{{ session('status') }}</p> @endif
        @if ($errors->any()) <div class="errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
        <form method="post" action="{{ route('verification.verify') }}" autocomplete="off">
            @csrf
            <label for="code">{{ __('ui.verification_code') }}</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" required autofocus>
            <button class="button" type="submit">{{ __('ui.activate_account') }}</button>
        </form>
        <form method="post" action="{{ route('verification.send') }}" class="auth-secondary-form">
            @csrf
            <button class="button secondary" type="submit">{{ __('ui.resend_verification_code') }}</button>
        </form>
        <form method="post" action="{{ route('logout') }}" class="auth-secondary-form">
            @csrf
            <button class="auth-text-button" type="submit">{{ __('ui.logout') }}</button>
        </form>
    </section>
</main>
@endsection
