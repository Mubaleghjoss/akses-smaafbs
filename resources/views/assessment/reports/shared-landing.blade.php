<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ asset('favicon.ico') }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <style>
        :root { --ink: #15352b; --accent: #b38a35; --paper: #f7f4eb; }
        * { box-sizing: border-box; }
        body { align-items: center; background: radial-gradient(circle at top right, #e6d8ad, transparent 42%), var(--paper); color: var(--ink); display: flex; font-family: Georgia, serif; justify-content: center; margin: 0; min-height: 100vh; padding: 24px; }
        main { background: rgba(255,255,255,.9); border: 1px solid #d9cfb3; box-shadow: 0 18px 48px rgba(21,53,43,.16); max-width: 540px; padding: 42px 34px; text-align: center; width: 100%; }
        .eyebrow { color: var(--accent); font: 700 12px/1.2 Arial, sans-serif; letter-spacing: .15em; text-transform: uppercase; }
        h1 { font-size: clamp(26px, 6vw, 38px); margin: 14px 0 8px; }
        p { font-family: Arial, sans-serif; line-height: 1.6; }
        .student { font-weight: bold; margin: 26px 0 6px; }
        .actions { display: grid; gap: 12px; margin-top: 28px; }
        a { background: var(--ink); color: white; font: 700 15px Arial, sans-serif; padding: 14px 18px; text-decoration: none; }
        a:last-child { background: transparent; border: 1px solid var(--ink); color: var(--ink); }
    </style>
</head>
<body>
<main>
    <div class="eyebrow">SMA Al Furqon Boarding School</div>
    <h1>Preview Rapor {{ $type }}</h1>
    <p class="student">{{ $student }}</p>
    <p>{{ $class }}@if(filled($period)) &middot; {{ $period }}@endif</p>
    <p>Tautan ini hanya untuk melihat dan mengunduh rapor siswa.</p>
    <div class="actions">
        <a href="{{ $previewUrl }}" target="_blank" rel="noopener">Lihat PDF Rapor</a>
        <a href="{{ $downloadUrl }}">Download PDF</a>
    </div>
</main>
</body>
</html>
