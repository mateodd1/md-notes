<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0e0f12" media="(prefers-color-scheme: dark)">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <title>{{ $title ?? 'md-notes' }}</title>
    <script>
        try { const theme = localStorage.getItem('md-notes-theme') || 'system'; if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (_) { if (window.matchMedia?.('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark'); }
    </script>
    <style>
        :root { color-scheme:light; --ink:#1e293b; --muted:#64748b; --line:#e2e8f0; --paper:#fff; --canvas:#f8fafc; --accent:#0284c7; --accent-soft:#e0f2fe; --danger:#be123c; --hover:#f1f5f9; --soft-danger:#fff1f2; --preview:#f1f5f9; --shadow-card:0 4px 16px #0f172a0a,0 1px 3px #0f172a08; }
        html.dark { color-scheme:dark; --ink:#f3f4f6; --muted:#9ca3af; --line:#252832; --paper:#16171b; --canvas:#0e0f12; --accent:#00d2ff; --accent-soft:#00d2ff1f; --danger:#f5b7b7; --hover:#1c1e24; --soft-danger:#f5b7b71f; --preview:#20222a; --shadow-card:0 4px 20px #00000040; }
        * { box-sizing:border-box; } html,body { min-height:100%; } body { margin:0; color:var(--ink); background:var(--canvas); font:15px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; -webkit-font-smoothing:antialiased; transition:background-color .2s ease,color .2s ease; }
        a { color:inherit; text-decoration:none; } button,input,textarea,select { font:inherit; } button { cursor:pointer; } .button { border:1px solid transparent; border-radius:10px; background:var(--accent); color:#fff; padding:9px 13px; font-weight:650; box-shadow:0 1px 2px #0f172a1a; transition:transform .16s ease,filter .16s ease,border-color .16s ease,background-color .16s ease; } .button:hover { filter:brightness(1.04); } .button:active { transform:translateY(1px); } html.dark .button:not(.secondary):not(.danger) { color:#0c0d0e; } .button.secondary { background:var(--paper); color:var(--ink); border-color:var(--line); box-shadow:none; } .button.secondary:hover,.button.secondary:focus-visible { border-color:var(--accent); background:var(--accent-soft); color:var(--accent); } .button.danger { color:var(--danger); background:var(--soft-danger); box-shadow:none; } .button.danger:hover,.button.danger:focus-visible { border-color:var(--danger); filter:none; } html.dark .button.secondary { color:var(--ink); } html.dark .button.danger { color:var(--danger); } .button.small { padding:6px 9px; font-size:13px; }
        .flash { margin:12px 16px; border-radius:10px; padding:10px 13px; background:#dcfce7; color:#166534; } .errors { margin:12px 16px; border-radius:10px; padding:10px 13px; background:var(--soft-danger); color:var(--danger); } .errors ul { margin:0; padding-left:18px; }
        .auth-shell { min-height:100vh; display:grid; place-items:center; padding:24px; background:radial-gradient(circle at 78% 0,#00d2ff18 0,transparent 31rem),var(--canvas); } .auth-card { width:min(100%,410px); padding:32px; background:var(--paper); border:1px solid var(--line); box-shadow:var(--shadow-card); border-radius:18px; } .auth-brand { color:var(--accent); font-weight:800; letter-spacing:-.03em; } .auth-card h1 { margin:8px 0 4px; font-size:27px; letter-spacing:-.04em; } .auth-card p { color:var(--muted); margin:0 0 25px; } label { display:block; font-size:13px; font-weight:650; margin:14px 0 5px; } input,textarea,select { width:100%; border:1px solid var(--line); border-radius:10px; background:var(--paper); color:var(--ink); padding:10px 11px; outline:none; transition:border-color .16s ease,box-shadow .16s ease; } input:focus,textarea:focus,select:focus { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft); } .auth-card .button { width:100%; margin-top:22px; } .form-foot { text-align:center; color:var(--muted); margin:18px 0 0 !important; font-size:14px; } .form-foot a { color:var(--accent); font-weight:650; }
        .modal:target { animation:md-notes-modal-backdrop-in .18s ease-out both; }.modal:target .modal-card { animation:md-notes-modal-card-in .22s cubic-bezier(.2,.8,.2,1) both; }.modal.is-closing { display:grid; animation:md-notes-modal-backdrop-out .16s ease-in both; }.modal.is-closing .modal-card { animation:md-notes-modal-card-out .16s ease-in both; }@keyframes md-notes-modal-backdrop-in { from { opacity:0; } to { opacity:1; } }@keyframes md-notes-modal-card-in { from { opacity:0; transform:translateY(12px) scale(.98); } to { opacity:1; transform:translateY(0) scale(1); } }@keyframes md-notes-modal-backdrop-out { from { opacity:1; } to { opacity:0; } }@keyframes md-notes-modal-card-out { from { opacity:1; transform:translateY(0) scale(1); } to { opacity:0; transform:translateY(8px) scale(.985); } }@media (prefers-reduced-motion:reduce) { .modal:target,.modal:target .modal-card,.modal.is-closing,.modal.is-closing .modal-card { animation:none; } }
    </style>
    @stack('head')
</head>
<body>
    @yield('body')
</body>
</html>
