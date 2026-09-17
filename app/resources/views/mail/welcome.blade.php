<!doctype html>
<html lang="{{ $locale }}">
<body style="margin:0;background:#f6f7fb;color:#1d2433;font:16px/1.55 Arial,sans-serif">
    <main style="max-width:560px;margin:32px auto;padding:32px;background:#fff;border:1px solid #dde2ea;border-radius:16px">
        <div style="color:#5e56e9;font-weight:800">✦ md-notes</div>
        @if ($locale === 'es')
            <h1 style="margin:12px 0 8px;font-size:26px">Bienvenido, {{ $user->name }}</h1>
            <p style="margin:0 0 16px">Tu espacio privado de notas ya está listo.</p>
            <p style="margin:0 0 22px">Crea carpetas para tus asignaturas, escribe en Markdown y consulta el historial de tus cambios cuando lo necesites.</p>
            <a href="{{ route('notes.index') }}" style="display:inline-block;padding:10px 14px;border-radius:9px;background:#5e56e9;color:#fff;text-decoration:none;font-weight:700">Abrir md-notes</a>
        @else
            <h1 style="margin:12px 0 8px;font-size:26px">Welcome, {{ $user->name }}</h1>
            <p style="margin:0 0 16px">Your private notes space is ready.</p>
            <p style="margin:0 0 22px">Create folders for your classes, write in Markdown, and view a history of your changes whenever you need it.</p>
            <a href="{{ route('notes.index') }}" style="display:inline-block;padding:10px 14px;border-radius:9px;background:#5e56e9;color:#fff;text-decoration:none;font-weight:700">Open md-notes</a>
        @endif
    </main>
</body>
</html>
