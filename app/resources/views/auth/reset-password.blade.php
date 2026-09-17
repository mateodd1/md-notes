@extends('layouts.app')

@section('body')
<main class="auth-shell">
    <section class="auth-card">
        <div class="auth-brand">✦ md-notes</div>
        <h1>{{ __('ui.set_new_password') }}</h1>
        <p>{{ __('ui.new_password_help') }}</p>
        @if ($errors->any()) <div class="errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
        <form method="post" action="{{ route('password.update') }}" autocomplete="off">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="email">{{ __('ui.email') }}</label><input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="off" autocapitalize="none" spellcheck="false" required autofocus>
            <label for="password">{{ __('ui.new_password') }}</label><input id="password" name="password" type="password" minlength="8" maxlength="128" autocomplete="new-password" required>
            <label for="password_confirmation">{{ __('ui.repeat_new_password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" maxlength="128" autocomplete="new-password" required>
            <button class="button" type="submit">{{ __('ui.save_password') }}</button>
        </form>
    </section>
</main>
@endsection
