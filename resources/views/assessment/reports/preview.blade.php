<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pratinjau Rapor</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; color: #172033; font: 14px/1.4 Georgia, serif; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 12px 18px; background: #fff; border-bottom: 1px solid #cbd5e1; font-family: system-ui, sans-serif; }
        h1 { margin: 0; font-size: 16px; font-weight: 650; }
        a { display: inline-block; padding: 8px 13px; border-radius: 4px; background: #1e3a5f; color: #fff; text-decoration: none; font-weight: 600; }
        main { height: calc(100vh - 57px); padding: 18px; }
        iframe { display: block; width: 100%; height: 100%; border: 1px solid #cbd5e1; background: #fff; box-shadow: 0 2px 8px rgb(15 23 42 / 12%); }
        @media print { header { display: none; } main { height: 100vh; padding: 0; } iframe { border: 0; box-shadow: none; } }
    </style>
</head>
<body>
    <header>
        <h1>Pratinjau Rapor</h1>
        <a href="{{ $downloadUrl }}">Download PDF</a>
    </header>
    <main>
        <iframe src="{{ $streamUrl }}" title="{{ $filename }}" loading="eager"></iframe>
    </main>
</body>
</html>
