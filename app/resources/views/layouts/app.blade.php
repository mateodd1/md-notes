<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'md-notes' }}</title>
    <script>
        try { const theme = localStorage.getItem('md-notes-theme') || 'system'; if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (_) { if (window.matchMedia?.('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark'); }
    </script>
    <style>
        :root { color-scheme: light; --ink:#1d2433; --muted:#687184; --line:#dde2ea; --paper:#fff; --canvas:#f6f7fb; --accent:#5e56e9; --accent-soft:#ecebff; --danger:#bd3153; --hover:#f0f0f7; --soft-danger:#fff0f3; --preview:#f3f4f8; }
        html.dark { color-scheme: dark; --ink:#edf0f8; --muted:#aab3c4; --line:#30394a; --paper:#161c28; --canvas:#0f131d; --accent:#9590ff; --accent-soft:#2c2b59; --danger:#ff9aac; --hover:#222b3a; --soft-danger:#3c222b; --preview:#202938; }
        * { box-sizing: border-box; } body { margin:0; color:var(--ink); background:var(--canvas); font:15px/1.5 Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        a { color:inherit; text-decoration:none; } button, input, textarea, select { font:inherit; } button { cursor:pointer; } .button { border:0; border-radius:9px; background:var(--accent); color:white; padding:9px 13px; font-weight:650; } .button.secondary { background:var(--paper); color:var(--ink); border:1px solid var(--line); } .button.danger { color:var(--danger); background:var(--soft-danger); } .button.small { padding:6px 9px; font-size:13px; }
        .flash { margin:12px 16px; border-radius:9px; padding:10px 13px; background:#eaf8ef; color:#176235; } .errors { margin:12px 16px; border-radius:9px; padding:10px 13px; background:var(--soft-danger); color:var(--danger); } .errors ul { margin:0; padding-left:18px; }
        .auth-shell { min-height:100vh; display:grid; place-items:center; padding:24px; background:radial-gradient(circle at 80% 0, #dedbff 0, transparent 30rem), var(--canvas); } .auth-card { width:min(100%, 410px); padding:32px; background:var(--paper); border:1px solid var(--line); box-shadow:0 20px 50px #25215c18; border-radius:18px; } .auth-brand { color:var(--accent); font-weight:800; letter-spacing:-.03em; } .auth-card h1 { margin:8px 0 4px; font-size:27px; letter-spacing:-.04em; } .auth-card p { color:var(--muted); margin:0 0 25px; } label { display:block; font-size:13px; font-weight:650; margin:14px 0 5px; } input, textarea, select { width:100%; border:1px solid var(--line); border-radius:9px; background:var(--paper); color:var(--ink); padding:10px 11px; outline:none; } input:focus, textarea:focus, select:focus { border-color:var(--accent); box-shadow:0 0 0 3px #5e56e91c; } .auth-card .button { width:100%; margin-top:22px; } .form-foot { text-align:center; color:var(--muted); margin:18px 0 0 !important; font-size:14px; } .form-foot a { color:var(--accent); font-weight:650; }
    </style>
    @stack('head')
</head>
<body>
    @yield('body')
</body>
</html>
