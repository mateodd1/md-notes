@extends('layouts.app')

@section('body')
<main class="auth-shell">
    <section class="auth-card">
        <div class="auth-brand"><x-icon name="spark" class="brand-icon" />md-notes</div>
        <h1>{{ __('ui.reset_password') }}</h1>
        <p>{{ __('ui.reset_password_help') }}</p>
        @if (session('status')) <div class="flash">{{ session('status') }}</div> @endif
        @if ($errors->any()) <div class="errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
        <form method="post" action="{{ route('password.email') }}" autocomplete="off">
            @csrf
            <label for="email">{{ __('ui.email') }}</label><input id="email" name="email" type="email" value="{{ is_string(old('email')) ? old('email') : '' }}" autocomplete="off" autocapitalize="none" spellcheck="false" required autofocus>
            <button class="button" type="submit">{{ __('ui.send_reset_link') }}</button>
        </form>
        <p class="form-foot"><a href="{{ route('login') }}">{{ __('ui.back_to_login') }}</a></p>
    </section>
</main>
@endsection
