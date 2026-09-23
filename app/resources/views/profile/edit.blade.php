@extends('layouts.app')

@section('body')
<main class="settings-shell">
    <header class="settings-header"><div><a class="settings-brand" href="{{ route('notes.index') }}"><x-icon name="spark" class="brand-icon" />md-notes</a><h1>{{ __('ui.profile_settings') }}</h1><p>{{ $user->email }}</p></div><a class="button secondary small back-to-notes" href="{{ route('notes.index') }}">{{ __('ui.back_to_notes') }}</a></header>
    @if ($errors->any())<div class="errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if (session('status'))<div id="toast" class="toast" role="status"><span>{{ session('status') }}</span><button type="button" aria-label="{{ __('ui.close') }}"><x-icon name="close" class="close-icon" /></button></div>@endif
    <section class="settings-grid">
        <article class="settings-card settings-card-wide"><h2>{{ __('ui.name') }}</h2><p>{{ __('ui.your_name') }}</p><form method="post" action="{{ route('profile.name') }}">@csrf @method('PATCH')<label for="name">{{ __('ui.name') }}</label><input id="name" name="name" minlength="3" maxlength="80" value="{{ is_string(old('name', $user->name)) ? old('name', $user->name) : $user->name }}" required autocomplete="name"><button class="button" type="submit">{{ __('ui.save_name') }}</button></form></article>
        <article class="settings-card"><h2>{{ __('ui.storage') }}</h2><p>{{ __('ui.storage_quota_help', ['limit' => $quota['limit_human']]) }}</p><div class="storage-details"><strong>{{ $quota['used_human'] }}</strong><span>{{ $quota['used_human'] }} / {{ $quota['limit_human'] }}</span></div><div class="storage-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $quota['percentage'] }}"><span style="width:{{ $quota['percentage'] }}%"></span></div></article>
        <article class="settings-card"><h2>{{ __('ui.password') }}</h2><p>{{ __('ui.password_help') }}</p>
            @if (session('password_code_requested'))
                <form class="code-form" method="post" action="{{ route('profile.password') }}" autocomplete="off">@csrf @method('PATCH')<p>{{ __('ui.password_code_help', ['email' => $user->email]) }}</p><label for="password-code">{{ __('ui.verification_code') }}</label><input id="password-code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus><label for="new-password">{{ __('ui.new_password') }}</label><input id="new-password" name="new_password" type="password" minlength="8" maxlength="128" autocomplete="new-password" required><label for="new-password-confirmation">{{ __('ui.repeat_new_password') }}</label><input id="new-password-confirmation" name="new_password_confirmation" type="password" minlength="8" maxlength="128" autocomplete="new-password" required><button class="button" type="submit">{{ __('ui.save_password') }}</button></form>
            @else
                <form method="post" action="{{ route('profile.password.code') }}">@csrf<button class="button" type="submit">{{ __('ui.change_password') }}</button></form>
            @endif
        </article>
        <article class="settings-card"><h2>{{ __('ui.account_export') }}</h2><p>{{ __('ui.account_export_help') }}</p><form method="post" action="{{ route('profile.account-exports.store') }}">@csrf<button class="button" type="submit">{{ __('ui.request_account_export') }}</button></form></article>
        <article class="settings-card danger-card"><h2>{{ __('ui.delete_account') }}</h2><p>{{ __('ui.delete_account_help') }}</p><a class="button danger" href="#delete-account">{{ __('ui.delete_my_account') }}</a></article>
        <article class="settings-card settings-card-wide"><h2>{{ __('ui.api_access') }}</h2><p>{{ __('ui.api_access_help') }}</p>
            @if (session('api_token_created'))<div class="api-secret"><strong>{{ __('ui.api_token_copy_once') }}</strong><code>{{ session('api_token_created') }}</code></div>@endif
            <form method="post" action="{{ route('profile.api-tokens.store') }}" autocomplete="off">@csrf<label for="api-token-name">{{ __('ui.api_token_name') }}</label><input id="api-token-name" name="name" maxlength="80" required placeholder="{{ __('ui.api_token_name_placeholder') }}"><button class="button" type="submit">{{ __('ui.create_api_token') }}</button></form>
            <p class="api-help"><a href="{{ route('documentation') }}">{{ __('ui.api_documentation') }}</a></p>
            @foreach ($apiTokens as $apiToken)<div class="api-token"><div><strong>{{ $apiToken->name }}</strong><span>{{ $apiToken->last_used_at ? __('ui.api_token_last_used', ['date' => $apiToken->last_used_at->isoFormat('L LT')]) : __('ui.api_token_never_used') }}</span></div><form method="post" action="{{ route('profile.api-tokens.destroy', ['token' => $apiToken]) }}">@csrf @method('DELETE')<button class="button danger small" type="submit">{{ __('ui.revoke') }}</button></form></div>@endforeach
        </article>
    </section>
</main>
<div id="delete-account" class="modal"><section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="delete-account-title"><h2 id="delete-account-title">{{ __('ui.delete_account_question') }}</h2><p>{{ __('ui.delete_account_confirm') }}</p><form method="post" action="{{ route('profile.destroy') }}">@csrf @method('DELETE')<label for="delete-password">{{ __('ui.password') }}</label><input id="delete-password" name="current_password" type="password" required autocomplete="current-password"><div class="modal-footer"><a href="#">{{ __('ui.cancel') }}</a><button class="button danger" type="submit">{{ __('ui.delete_account') }}</button></div></form></section></div>
<script>const toast = document.getElementById('toast'); if (toast) { const dismiss = () => { toast.classList.add('hiding'); setTimeout(() => toast.remove(), 220); }; toast.querySelector('button').onclick = dismiss; setTimeout(dismiss, 3000); }</script>
@endsection
