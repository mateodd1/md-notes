<!doctype html>
<html lang="{{ $mailLocale }}">
<body style="margin:0;background:#f6f7fb;color:#1d2433;font:16px/1.55 Arial,sans-serif">
    <main style="max-width:560px;margin:32px auto;padding:32px;background:#fff;border:1px solid #dde2ea;border-radius:16px">
        <div style="color:#5e56e9;font-weight:800">✦ md-notes</div>
        @if ($mailLocale === 'es')
            <h1 style="margin:12px 0 8px;font-size:26px">Tu descarga está lista</h1>
            <p style="margin:0 0 16px">Hola, {{ $user->name }}. Hemos preparado un archivo ZIP con la información de tu cuenta, notas, adjuntos, historial y enlaces compartidos.</p>
            <a href="{{ $downloadUrl }}" style="display:inline-block;padding:10px 14px;border-radius:9px;background:#5e56e9;color:#fff;text-decoration:none;font-weight:700">Descargar mis datos</a>
            <p style="margin:22px 0 0;color:#687184">Por seguridad, este enlace caduca en 24 horas. Si no solicitaste esta descarga, puedes ignorar este correo.</p>
        @else
            <h1 style="margin:12px 0 8px;font-size:26px">Your download is ready</h1>
            <p style="margin:0 0 16px">Hi {{ $user->name }}. We prepared a ZIP file with your account information, notes, attachments, history, and shared links.</p>
            <a href="{{ $downloadUrl }}" style="display:inline-block;padding:10px 14px;border-radius:9px;background:#5e56e9;color:#fff;text-decoration:none;font-weight:700">Download my data</a>
            <p style="margin:22px 0 0;color:#687184">For security, this link expires in 24 hours. If you did not request this download, you can ignore this email.</p>
        @endif
    </main>
</body>
</html>
