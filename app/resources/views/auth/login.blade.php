@extends('layouts.app')

@section('body')
<main class="auth-shell">
    <section class="auth-card">
        <div class="auth-brand"><x-icon name="spark" class="brand-icon" />md-notes</div>
        <h1>{{ __('ui.welcome') }}</h1>
        @if ($errors->any()) <div class="errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
        <form method="post" action="{{ route('login.store') }}" autocomplete="off">
            @csrf
            <label for="email">{{ __('ui.email') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="off" autocapitalize="none" spellcheck="false" data-lpignore="true" data-1p-ignore="true" required autofocus>
            <label for="password">{{ __('ui.password') }}</label><input id="password" name="password" type="password" autocomplete="off" data-lpignore="true" data-1p-ignore="true" required>
            <label class="checkbox-label"><input name="remember" type="checkbox"> {{ __('ui.remember_me') }}</label>
            <button class="button" type="submit">{{ __('ui.login') }}</button>
        </form>
        <p class="form-foot"><a href="{{ route('password.request') }}">{{ __('ui.forgot_password') }}</a></p>
        <p class="form-foot">{{ __('ui.no_account') }} <a href="{{ route('register') }}">{{ __('ui.sign_up') }}</a></p>
    </section>
</main>
@endsection
