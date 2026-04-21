<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Inventaris BOSP</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #111827; background: #f8fafc; }
        .page { max-width: 1200px; margin: 0 auto; background: #fff; padding: 24px; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); }
        .toolbar { display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 16px; }
        .btn { display: inline-block; padding: 10px 16px; border-radius: 999px; background: #0f172a; color: #fff; text-decoration: none; font-size: 13px; font-weight: 700; }
        .btn.secondary { background: #e2e8f0; color: #0f172a; }
        h1, h2, p { margin: 0; }
        .letterhead { display: flex; align-items: center; gap: 16px; border-bottom: 3px solid #0f172a; padding-bottom: 14px; margin-bottom: 16px; }
        .letterhead img { width: 72px; height: 72px; object-fit: contain; }
        .letterhead-copy { flex: 1; text-align: center; }
        .letterhead-copy h2 { font-size: 24px; text-transform: uppercase; }
        .letterhead-copy p { margin-top: 4px; font-size: 13px; color: #475569; }
        .title { text-align: center; margin-bottom: 18px; }
        .title h1 { font-size: 22px; text-transform: uppercase; }
        .title p { margin-top: 6px; color: #475569; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border: 1px solid #0f172a; padding: 8px 9px; vertical-align: top; }
        thead th { background: #fff200; text-align: center; }
        .num, .money { white-space: nowrap; }
        .money { text-align: right; }
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
            <a class="btn secondary" href="{{ route('admin.sarpras-bosp-inventories.export') }}" target="_blank" rel="noreferrer">Export Excel</a>
            <a class="btn secondary" href="{{ route('admin.sarpras-bosp-inventories.pdf') }}" target="_blank" rel="noreferrer">Download PDF</a>
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
            <h1>Daftar Inventaris BOSP</h1>
            <p>{{ $schoolName }}</p>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No Urut</th>
                        <th>Nama Barang</th>
                        <th>Quality</th>
                        <th>Bulan Beli</th>
                        <th>Tahun Beli</th>
                        <th>Kode Barang</th>
                        <th>Lokasi Barang</th>
                        <th>Tanggal Datang</th>
                        <th>Total Harga</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>
                            <td class="num">{{ $record->nomor_urut ?: '-' }}</td>
                            <td>{{ $record->nama_barang }}</td>
                            <td class="num">{{ $record->quality }}</td>
                            <td>{{ \App\Filament\Resources\SarprasBospInventoryResource::bulanOptions()[$record->bulan_beli] ?? '-' }}</td>
                            <td class="num">{{ $record->tahun_beli ?: '-' }}</td>
                            <td>{{ $record->kode_barang ?: '-' }}</td>
                            <td>{{ $record->lokasi_barang ?: '-' }}</td>
                            <td>{{ $record->tanggal_datang?->translatedFormat('d F Y') ?: '-' }}</td>
                            <td class="money">Rp {{ number_format((float) ($record->total_harga ?? 0), 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center;">Belum ada data inventaris BOSP.</td>
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
