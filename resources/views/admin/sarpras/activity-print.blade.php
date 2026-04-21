<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kegiatan Sarpras</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #111827; background: #f8fafc; }
        .page { max-width: 1280px; margin: 0 auto; background: #fff; padding: 24px; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); }
        .toolbar { display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 16px; }
        .btn { display: inline-block; padding: 10px 16px; border-radius: 999px; background: #0f172a; color: #fff; text-decoration: none; font-size: 13px; font-weight: 700; border: 0; cursor: pointer; }
        .btn.secondary { background: #e2e8f0; color: #0f172a; }
        .letterhead { display: flex; align-items: center; gap: 16px; border-bottom: 3px solid #0f172a; padding-bottom: 14px; margin-bottom: 16px; }
        .letterhead img { width: 72px; height: 72px; object-fit: contain; }
        .letterhead-copy { flex: 1; text-align: center; }
        .letterhead-copy h2 { margin: 0; font-size: 24px; text-transform: uppercase; }
        .letterhead-copy p { margin: 4px 0 0; font-size: 13px; color: #475569; }
        .title { margin-bottom: 14px; }
        .title h1 { margin: 0; text-align: center; font-size: 22px; text-transform: uppercase; }
        .title p { margin: 6px 0 0; text-align: center; font-size: 13px; color: #475569; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid #0f172a; padding: 8px 9px; vertical-align: top; }
        thead th { background: #fff200; text-align: center; }
        .center { text-align: center; }
        .photo { width: 120px; height: 84px; object-fit: cover; display: block; margin: 0 auto; border: 1px solid #94a3b8; }
        @media (max-width: 768px) {
            body { padding: 10px; }
            .page { padding: 14px; }
            .toolbar { flex-direction: column; }
            .letterhead { flex-direction: column; text-align: center; }
            .table-wrap { overflow-x: auto; }
        }
        @media print {
            body { background: #fff; padding: 0; }
            .page { box-shadow: none; max-width: none; padding: 0; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="page">
        @unless($pdfMode ?? false)
        <div class="toolbar">
            <a class="btn secondary" href="{{ route('admin.sarpras-activities.export') }}" target="_blank" rel="noreferrer">Export Excel</a>
            <a class="btn secondary" href="{{ route('admin.sarpras-activities.pdf') }}" target="_blank" rel="noreferrer">Download PDF</a>
            <button class="btn" type="button" onclick="window.print()">Print / Simpan PDF</button>
        </div>
        @endunless

        <div class="letterhead">
            @if(!empty($letterhead['logo_src']))
                <img src="{{ $letterhead['logo_src'] }}" alt="Logo sekolah">
            @endif
            <div class="letterhead-copy">
                <h2>{{ $letterhead['site_name'] ?? $schoolName }}</h2>
                <p>{{ $letterhead['address'] ?? 'Alamat sekolah belum diatur.' }}</p>
                <p>{{ collect([$letterhead['phone'] ?? null, $letterhead['email'] ?? null])->filter()->implode(' | ') }}</p>
            </div>
        </div>

        <div class="title">
            <h1>Kegiatan Sarpras</h1>
            <p>{{ $schoolName }}</p>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th rowspan="2">No</th>
                        <th rowspan="2">Tanggal Pengerjaan</th>
                        <th rowspan="2">Perbaikan</th>
                        <th rowspan="2">PJ</th>
                        <th rowspan="2">Hasil Akhir</th>
                        <th colspan="2">Foto Kegiatan</th>
                        <th rowspan="2">Pelaksana (Paraf)</th>
                    </tr>
                    <tr>
                        <th>Sebelum</th>
                        <th>Sesudah</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $index => $record)
                        <tr>
                            <td class="center">{{ $index + 1 }}</td>
                            <td>{{ $record->tanggal_pengerjaan?->translatedFormat('d F Y') ?: '-' }}</td>
                            <td>{{ $record->perbaikan }}</td>
                            <td>{{ $record->penanggung_jawab ?: '-' }}</td>
                            <td>{{ $record->hasil_akhir ?: '-' }}</td>
                            <td class="center">
                                @if($record->foto_sebelum_print_src ?? $record->fotoSebelumUrl())
                                    <img class="photo" src="{{ $record->foto_sebelum_print_src ?? $record->fotoSebelumUrl() }}" alt="Foto sebelum">
                                @else
                                    -
                                @endif
                            </td>
                            <td class="center">
                                @if($record->foto_sesudah_print_src ?? $record->fotoSesudahUrl())
                                    <img class="photo" src="{{ $record->foto_sesudah_print_src ?? $record->fotoSesudahUrl() }}" alt="Foto sesudah">
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $record->pelaksana_paraf ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="center">Belum ada kegiatan sarpras.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(($printMode ?? false) && !($pdfMode ?? false))
        <script>
            window.addEventListener('load', () => window.print(), { once: true });
        </script>
    @endif
</body>
</html>
