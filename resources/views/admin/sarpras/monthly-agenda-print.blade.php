<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Bulanan Sarpras</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #111827; background: #f8fafc; }
        .page { max-width: 1100px; margin: 0 auto; background: #fff; padding: 24px; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); }
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
        .check { font-size: 18px; font-weight: 700; }
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
            <a class="btn secondary" href="{{ route('admin.sarpras-monthly-agendas.export') }}" target="_blank" rel="noreferrer">Export Excel</a>
            <a class="btn secondary" href="{{ route('admin.sarpras-monthly-agendas.pdf') }}" target="_blank" rel="noreferrer">Download PDF</a>
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
            <h1>Agenda Bulanan Sarpras</h1>
            <p>{{ $schoolName }}</p>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th rowspan="2">No</th>
                        <th rowspan="2">Jenis Kegiatan</th>
                        <th colspan="2">Tindak Lanjut</th>
                        <th rowspan="2">PJ</th>
                    </tr>
                    <tr>
                        <th>Sudah</th>
                        <th>Belum</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>
                            <td class="center">{{ $record->urutan }}</td>
                            <td>
                                {{ $record->jenis_kegiatan }}
                                @if($record->bulan_agenda)
                                    <div style="margin-top: 4px; color: #475569; font-size: 12px;">{{ $record->bulan_agenda->translatedFormat('F Y') }}</div>
                                @endif
                            </td>
                            <td class="center check">{{ $record->tindak_lanjut_status === \App\Models\SarprasMonthlyAgenda::STATUS_SUDAH ? 'v' : '' }}</td>
                            <td class="center check">{{ $record->tindak_lanjut_status === \App\Models\SarprasMonthlyAgenda::STATUS_BELUM ? 'v' : '' }}</td>
                            <td>{{ $record->penanggung_jawab ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="center">Belum ada agenda bulanan sarpras.</td>
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
