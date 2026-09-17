<!doctype html>
<html lang="{{ $locale }}">
<body style="margin:0;background:#f6f7fb;color:#1d2433;font:16px/1.55 Arial,sans-serif">
    <main style="max-width:560px;margin:32px auto;padding:32px;background:#fff;border:1px solid #dde2ea;border-radius:16px">
        <div style="color:#5e56e9;font-weight:800">✦ md-notes</div>
        @if ($locale === 'es')
            <h1 style="margin:12px 0 8px;font-size:26px">Código de seguridad</h1>
            <p style="margin:0 0 16px">Hola, {{ $user->name }}. Usa este código para {{ $purpose === 'password' ? 'cambiar tu contraseña' : 'confirmar tu correo electrónico nuevo' }}:</p>
            <p style="margin:20px 0;padding:14px;border-radius:10px;background:#f3f4f8;font-size:28px;font-weight:800;letter-spacing:7px;text-align:center">{{ $code }}</p>
            <p style="margin:0;color:#687184">Caduca en 15 minutos. Si no has solicitado este cambio, ignora este correo.</p>
        @else
            <h1 style="margin:12px 0 8px;font-size:26px">Security code</h1>
            <p style="margin:0 0 16px">Hi {{ $user->name }}. Use this code to {{ $purpose === 'password' ? 'change your password' : 'confirm your new email address' }}:</p>
            <p style="margin:20px 0;padding:14px;border-radius:10px;background:#f3f4f8;font-size:28px;font-weight:800;letter-spacing:7px;text-align:center">{{ $code }}</p>
            <p style="margin:0;color:#687184">It expires in 15 minutes. If you did not request this change, you can ignore this email.</p>
        @endif
    </main>
</body>
</html>
