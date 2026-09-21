<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin:18mm 17mm; }
        body { color:#1e293b; font:11px/1.6 "DejaVu Sans",sans-serif; word-wrap:break-word; }
        .note-path { color:#64748b; font-size:9px; border-bottom:1px solid #cbd5e1; padding-bottom:8px; margin-bottom:20px; }
        h1,h2,h3,h4 { line-height:1.3; page-break-after:avoid; }
        h1 { font-size:23px; } h2 { font-size:18px; } h3 { font-size:14px; }
        a { color:#0369a1; text-decoration:underline; }
        pre,code { font-family:"DejaVu Sans Mono",monospace; font-size:9px; }
        code { background:#f1f5f9; }
        pre { white-space:pre-wrap; word-wrap:break-word; background:#f1f5f9; padding:10px; }
        img { max-width:100%; max-height:220mm; height:auto; }
        table { width:100%; table-layout:fixed; border-collapse:collapse; font-size:9px; }
        th,td { border:1px solid #cbd5e1; padding:5px; word-wrap:break-word; }
        th { background:#f1f5f9; }
        blockquote { margin:12px 0; padding-left:12px; border-left:3px solid #94a3b8; color:#475569; }
        .missing-image { color:#64748b; font-style:italic; }
    </style>
</head>
<body>
    <div class="note-path">{{ $path }}</div>
    {!! $rendered !!}
</body>
</html>
