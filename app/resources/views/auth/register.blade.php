@extends('layouts.app')

@section('body')
<main class="auth-shell">
    <section class="auth-card">
        <div class="auth-brand"><x-icon name="spark" class="brand-icon" />md-notes</div>
        <h1>{{ __('ui.create_your_account') }}</h1>
        <p>{{ __('ui.private_space') }}</p>
        @if ($errors->any()) <div class="errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
        <form method="post" action="{{ route('register.store') }}" autocomplete="off">
            @csrf
            <label for="name">{{ __('ui.name') }}</label><input id="name" name="name" value="{{ old('name') }}" minlength="3" maxlength="80" autocomplete="off" data-lpignore="true" data-1p-ignore="true" required autofocus>
            <label for="email">{{ __('ui.email') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="off" autocapitalize="none" spellcheck="false" data-lpignore="true" data-1p-ignore="true" required>
            <label for="password">{{ __('ui.password') }} <span class="label-help">({{ __('ui.minimum_8') }})</span></label><input id="password" name="password" type="password" minlength="8" maxlength="128" autocomplete="new-password" required>
            <label for="password_confirmation">{{ __('ui.confirm_password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" maxlength="128" autocomplete="new-password" required>
            <button class="button" type="submit">{{ __('ui.sign_up') }}</button>
        </form>
        <p class="form-foot">{{ __('ui.already_account') }} <a href="{{ route('login') }}">{{ __('ui.login') }}</a></p>
    </section>
</main>
@endsection
