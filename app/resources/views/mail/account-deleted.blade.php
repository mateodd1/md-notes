<!doctype html>
<html lang="{{ $mailLocale }}">
<body style="margin:0;background:#f6f7fb;color:#1d2433;font:16px/1.55 Arial,sans-serif">
    <main style="max-width:560px;margin:32px auto;padding:32px;background:#fff;border:1px solid #dde2ea;border-radius:16px">
        <div style="color:#5e56e9;font-weight:800">✦ md-notes</div>
        @if ($mailLocale === 'es')
            <h1 style="margin:12px 0 8px;font-size:26px">Cuenta eliminada</h1>
            <p>Hola, {{ $recipientName }}. Tu cuenta de md-notes y sus datos se han eliminado.</p>
        @else
            <h1 style="margin:12px 0 8px;font-size:26px">Account deleted</h1>
            <p>Hi {{ $recipientName }}. Your md-notes account and its data have been deleted.</p>
        @endif
    </main>
</body>
</html>
