<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Berkas Guru - {{ $berkasGuru->guru?->nama ?? 'Berkas Guru' }}</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #0f1117;
            --panel: #171a22;
            --line: rgba(255, 255, 255, 0.08);
            --text: #f4f4f5;
            --muted: #a1a1aa;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: radial-gradient(circle at top right, rgba(245, 158, 11, 0.14), transparent 28%), var(--bg);
            color: var(--text);
        }
        .page { min-height: 100vh; display: grid; grid-template-rows: auto 1fr; }
        .toolbar {
            position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; gap: 1rem;
            justify-content: space-between; align-items: center; padding: 1rem 1.25rem;
            background: rgba(15, 17, 23, 0.92); border-bottom: 1px solid var(--line); backdrop-filter: blur(12px);
        }
        .meta { display: grid; gap: .25rem; }
        .eyebrow { font-size: .75rem; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); }
        h1 { margin: 0; font-size: 1.1rem; }
        .sub { color: var(--muted); font-size: .92rem; }
        .actions { display: flex; flex-wrap: wrap; gap: .75rem; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; text-decoration: none; color: var(--text);
            border: 1px solid var(--line); background: var(--panel); border-radius: 999px; padding: .72rem 1rem;
            font-weight: 600; transition: .15s ease;
        }
        .btn-primary { background: linear-gradient(135deg, #f59e0b, #d97706); color: #0f1117; border-color: transparent; }
        .viewer-wrap { padding: 1rem; display: grid; gap: 1rem; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .9rem; }
        .info-card { background: rgba(23, 26, 34, 0.86); border: 1px solid var(--line); border-radius: 18px; padding: 1rem; }
        .info-card .label { color: var(--muted); font-size: .8rem; margin-bottom: .4rem; }
        .viewer { min-height: calc(100vh - 220px); background: rgba(23, 26, 34, 0.92); border: 1px solid var(--line); border-radius: 24px; overflow: hidden; }
        .viewer iframe, .viewer img {
            width: 100%; height: calc(100vh - 220px); border: 0; display: block; object-fit: contain; background: #0b0d12;
        }
        .empty { min-height: calc(100vh - 220px); display: grid; place-items: center; padding: 2rem; text-align: center; color: var(--muted); }
        .pill {
            display: inline-flex; align-items: center; gap: .35rem; border-radius: 999px;
            background: rgba(34, 197, 94, 0.12); color: #86efac; padding: .35rem .7rem; font-size: .78rem; font-weight: 700;
        }
        @media (max-width: 768px) {
            .toolbar { padding: 1rem; }
            .viewer-wrap { padding: .9rem; }
            .viewer, .empty, .viewer iframe, .viewer img { min-height: calc(100vh - 280px); height: calc(100vh - 280px); }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="toolbar">
            <div class="meta">
                <span class="eyebrow">Preview Berkas Guru</span>
                <h1>{{ $berkasGuru->jenisBerkas?->nama_berkas ?? 'Dokumen Guru' }}</h1>
                <div class="sub">{{ $berkasGuru->guru?->nama ?? 'Guru / Tendik' }} - {{ basename((string) $berkasGuru->file_path) }}</div>
            </div>

            <div class="actions">
                <a href="{{ url('/admin/berkas-gurus') }}" class="btn">Kembali</a>
                <a href="{{ $berkasGuru->resolvedFileUrl() }}" target="_blank" class="btn">Buka File Asli</a>
                <a href="{{ route('admin.berkas-gurus.download', $berkasGuru) }}" class="btn btn-primary">Download</a>
            </div>
        </div>

        <div class="viewer-wrap">
            <div class="info-grid">
                <div class="info-card">
                    <div class="label">Guru / Tendik</div>
                    <div>{{ $berkasGuru->guru?->nama ?? '-' }}</div>
                </div>
                <div class="info-card">
                    <div class="label">Jenis Berkas</div>
                    <div>{{ $berkasGuru->jenisBerkas?->nama_berkas ?? '-' }}</div>
                </div>
                <div class="info-card">
                    <div class="label">Upload</div>
                    <div>{{ $berkasGuru->uploaded_at?->translatedFormat('d M Y H:i') ?? '-' }}</div>
                </div>
                <div class="info-card">
                    <div class="label">Mode Preview</div>
                    <div class="pill">
                        @if ($berkasGuru->isPdf()) PDF Full View
                        @elseif ($berkasGuru->isImage()) Gambar Full View
                        @else File Download
                        @endif
                    </div>
                </div>
            </div>

            <div class="viewer">
                @if ($berkasGuru->isPdf() && $berkasGuru->resolvedFileUrl())
                    <iframe src="{{ $berkasGuru->resolvedFileUrl() }}"></iframe>
                @elseif ($berkasGuru->isImage() && $berkasGuru->resolvedFileUrl())
                    <img src="{{ $berkasGuru->resolvedFileUrl() }}" alt="Preview berkas guru {{ $berkasGuru->guru?->nama }}">
                @else
                    <div class="empty">
                        <div>
                            <h2 style="margin-top: 0; color: var(--text);">Preview langsung belum tersedia</h2>
                            <p>Format file ini lebih aman dibuka melalui tombol download atau buka file asli.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>