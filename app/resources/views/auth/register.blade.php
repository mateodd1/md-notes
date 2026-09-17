@extends('layouts.app')

@section('body')
<main class="auth-shell">
    <section class="auth-card">
        <div class="auth-brand">✦ md-notes</div>
        <h1>{{ __('ui.create_your_account') }}</h1>
        <p>{{ __('ui.private_space') }}</p>
        @if ($errors->any()) <div class="errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
        <form method="post" action="{{ route('register.store') }}" autocomplete="off">
            @csrf
            <label for="name">{{ __('ui.name') }}</label><input id="name" name="name" value="{{ old('name') }}" autocomplete="off" data-lpignore="true" data-1p-ignore="true" required autofocus>
            <label for="email">{{ __('ui.email') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="off" autocapitalize="none" spellcheck="false" data-lpignore="true" data-1p-ignore="true" required>
            <label for="password">{{ __('ui.password') }} <span style="font-weight:400;color:var(--muted)">({{ __('ui.minimum_12') }})</span></label><input id="password" name="password" type="password" autocomplete="new-password" required>
            <label for="password_confirmation">{{ __('ui.repeat_new_password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            <button class="button" type="submit">{{ __('ui.sign_up') }}</button>
        </form>
        <p class="form-foot">{{ __('ui.already_account') }} <a href="{{ route('login') }}">{{ __('ui.login') }}</a></p>
    </section>
</main>
@endsection
