<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Inventaris Ruangan</title>
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
        h1 { margin: 0 0 10px; font-size: 22px; text-transform: uppercase; text-align: center; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 14px; }
        .meta td { border: 1px solid #0f172a; padding: 8px 10px; }
        .meta td:first-child { width: 30%; font-weight: 700; }
        table.grid { width: 100%; border-collapse: collapse; font-size: 13px; }
        .grid th, .grid td { border: 1px solid #0f172a; padding: 8px 9px; vertical-align: top; }
        .grid thead th { background: #0ea5e9; color: #000; text-align: center; }
        .signature { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 40px; margin-top: 36px; font-size: 14px; }
        .signature .name { margin-top: 72px; font-weight: 700; }
        @media (max-width: 768px) {
            body { padding: 10px; }
            .page { padding: 14px; }
            .toolbar { flex-direction: column; }
            .letterhead { flex-direction: column; text-align: center; }
            .table-wrap { overflow-x: auto; }
            .signature { grid-template-columns: 1fr; gap: 24px; }
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
            <a class="btn secondary" href="{{ route('admin.sarpras-room-inventories.export', $record) }}" target="_blank" rel="noreferrer">Export Excel</a>
            <a class="btn secondary" href="{{ route('admin.sarpras-room-inventories.pdf', $record) }}" target="_blank" rel="noreferrer">Download PDF</a>
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

        <h1>Daftar Inventaris Ruangan</h1>

        <table class="meta">
            <tr>
                <td>Nama Gedung :</td>
                <td>{{ $record->nama_gedung }}</td>
            </tr>
            <tr>
                <td>Nama Ruang :</td>
                <td>{{ $record->nama_ruang }}</td>
            </tr>
            <tr>
                <td>Nomor Ruang :</td>
                <td>{{ $record->nomor_ruang ?: '-' }}</td>
            </tr>
        </table>

        <div class="table-wrap">
            <table class="grid">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Nama Barang</th>
                        <th>Jumlah</th>
                        <th>Kondisi Barang</th>
                        <th>Ket</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($record->items as $item)
                        <tr>
                            <td style="text-align: center;">{{ $item->urutan }}</td>
                            <td>{{ $item->tanggal?->translatedFormat('l, F j, Y') ?: '-' }}</td>
                            <td>{{ $item->nama_barang }}</td>
                            <td style="text-align: center;">{{ $item->jumlah }}</td>
                            <td>{{ $item->kondisi_barang ?: '-' }}</td>
                            <td>{{ $item->keterangan ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center;">Belum ada item inventaris ruangan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="signature">
            <div>
                <div>Kepala Sekolah,</div>
                <div class="name">{{ $record->penanggung_jawab ?: '-' }}</div>
            </div>
            <div>
                <div>Mengetahui,<br>Sarpras {{ $schoolName }}</div>
                <div class="name">{{ $record->diketahui_oleh ?: '-' }}</div>
            </div>
        </div>
    </div>

    @if(($printMode ?? false) && !($pdfMode ?? false))
        <script>
            window.addEventListener('load', () => window.print(), { once: true });
        </script>
    @endif
</body>
</html>
