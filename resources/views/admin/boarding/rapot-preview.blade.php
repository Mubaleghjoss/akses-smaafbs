<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapot Boarding - {{ $payload['siswa']['nama'] ?? 'Murid' }}</title>
    <style>
        :root {
            --ink: #0f172a;
            --muted: #475569;
            --line: #cbd5e1;
            --soft: #f8fafc;
            --accent: #f59e0b;
            --paper: #ffffff;
            --bg: #f1f5f9;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: "Segoe UI", Tahoma, sans-serif;
            padding: 24px;
        }
        .page {
            max-width: 980px;
            margin: 0 auto;
            background: var(--paper);
            padding: 28px 30px 36px;
            box-shadow: 0 18px 48px rgba(15, 23, 42, 0.10);
        }
        .toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 999px;
            text-decoration: none;
            background: var(--ink);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            border: 0;
            cursor: pointer;
        }
        .button.secondary {
            background: #e2e8f0;
            color: var(--ink);
        }
        .letterhead {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 18px;
            align-items: center;
            border-bottom: 3px solid var(--ink);
            padding-bottom: 18px;
            margin-bottom: 18px;
        }
        .logo-box {
            width: 84px;
            height: 84px;
            border: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--paper);
            overflow: hidden;
        }
        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .letterhead-copy {
            text-align: center;
        }
        .letterhead-copy h1,
        .letterhead-copy h2,
        .letterhead-copy p {
            margin: 0;
        }
        .letterhead-copy h1 {
            font-size: 24px;
            letter-spacing: 0.08em;
        }
        .letterhead-copy h2 {
            margin-top: 4px;
            font-size: 17px;
            font-weight: 700;
        }
        .letterhead-copy p {
            margin-top: 7px;
            color: var(--muted);
            font-size: 13px;
        }
        .doc-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 18px;
        }
        .doc-chip {
            border: 1px solid var(--line);
            background: var(--soft);
            padding: 10px 12px;
            font-size: 12px;
        }
        .doc-chip strong {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .title-block {
            text-align: center;
            margin-bottom: 20px;
        }
        .title-block h3 {
            margin: 0;
            font-size: 22px;
        }
        .title-block p {
            margin: 6px 0 0;
            font-size: 13px;
            color: var(--muted);
        }
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }
        .panel {
            border: 1px solid var(--line);
            padding: 14px 16px;
        }
        .panel h4 {
            margin: 0 0 10px;
            font-size: 15px;
            text-transform: uppercase;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .meta-table td {
            padding: 6px 0;
            vertical-align: top;
        }
        .meta-table td:first-child {
            width: 42%;
            color: var(--muted);
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin: 16px 0 20px;
        }
        .stat {
            border: 1px solid var(--line);
            background: #fffbeb;
            padding: 12px;
            text-align: center;
        }
        .stat small {
            display: block;
            color: var(--muted);
            margin-bottom: 6px;
            font-size: 12px;
        }
        .stat strong {
            display: block;
            font-size: 24px;
        }
        .section {
            margin-top: 18px;
        }
        .section-title {
            margin: 0 0 10px;
            padding: 8px 12px;
            background: var(--ink);
            color: #fff;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .summary-box {
            border: 1px solid var(--line);
            padding: 12px 14px;
            min-height: 68px;
            white-space: pre-line;
            line-height: 1.6;
            font-size: 13px;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        table.grid th,
        table.grid td {
            border: 1px solid var(--line);
            padding: 8px 9px;
            vertical-align: top;
            text-align: left;
        }
        table.grid thead th {
            background: #f8fafc;
            font-weight: 700;
        }
        .two-col {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .group-title {
            margin: 12px 0 8px;
            font-size: 14px;
            font-weight: 700;
            color: var(--ink);
        }
        .signature-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
            margin-top: 20px;
            text-align: center;
            font-size: 13px;
        }
        .signature-box {
            min-height: 140px;
        }
        .signature-box .name {
            margin-top: 72px;
            font-weight: 700;
            text-decoration: underline;
        }
        .muted {
            color: var(--muted);
        }
        .center { text-align: center; }
        @media (max-width: 768px) {
            body { padding: 10px; }
            .page { padding: 16px; }
            .letterhead,
            .meta-grid,
            .stats,
            .two-col,
            .signature-grid,
            .doc-strip {
                grid-template-columns: 1fr;
            }
            .letterhead {
                text-align: center;
            }
            .logo-box {
                margin: 0 auto;
            }
        }
        @media print {
            body {
                padding: 0;
                background: #fff;
            }
            .page {
                max-width: none;
                box-shadow: none;
                padding: 0;
            }
            .toolbar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="toolbar">
            @unless($printMode)
                <a class="button secondary" href="{{ route('admin.boarding-rapots.export', $rapot) }}" target="_blank" rel="noreferrer">Export Excel</a>
                <a class="button secondary" href="{{ route('admin.boarding-rapots.print', $rapot) }}" target="_blank" rel="noreferrer">Mode Cetak</a>
            @endunless
            <button class="button" type="button" onclick="window.print()">Print / Simpan PDF</button>
        </div>

        <header class="letterhead">
            <div class="logo-box">
                @if(!empty($letterhead['logo_src']))
                    <img src="{{ $letterhead['logo_src'] }}" alt="Logo sekolah">
                @endif
            </div>
            <div class="letterhead-copy">
                <h1>{{ strtoupper((string) ($letterhead['site_name'] ?? $payload['school']['nama'] ?? 'SMA AFBS')) }}</h1>
                <h2>{{ strtoupper((string) ($payload['school']['boarding_label'] ?? 'BOARDING SCHOOL')) }}</h2>
                <p>{{ $letterhead['address'] ?? $payload['school']['alamat'] ?? 'Alamat sekolah belum diatur.' }}</p>
                <p>
                    {{ collect([$letterhead['phone'] ?? null, $letterhead['email'] ?? null])->filter()->implode(' | ') ?: 'Kontak sekolah belum diatur.' }}
                </p>
            </div>
        </header>

        <div class="doc-strip">
            <div class="doc-chip">
                <strong>Nomor Dokumen</strong>
                {{ $payload['rapot']['nomor_dokumen'] ?? '-' }}
            </div>
            <div class="doc-chip">
                <strong>Status Dokumen</strong>
                {{ $payload['rapot']['status_rapot'] ?? '-' }}
            </div>
            <div class="doc-chip">
                <strong>Dicetak</strong>
                {{ ($generatedAt ?? now())->translatedFormat('d M Y H:i') }}
            </div>
        </div>

        <section class="title-block">
            <h3>RAPOT BOARDING</h3>
            <p>Dokumen rekap perkembangan boarding murid per periode belajar</p>
        </section>

        <div class="meta-grid">
            <section class="panel">
                <h4>Identitas Murid</h4>
                <table class="meta-table">
                    <tr><td>Nama Murid</td><td>{{ $payload['siswa']['nama'] ?? '-' }}</td></tr>
                    <tr><td>Rombel</td><td>{{ $payload['siswa']['rombel'] ?? '-' }}</td></tr>
                    <tr><td>Jenis Kelamin</td><td>{{ $payload['siswa']['jk'] ?? '-' }}</td></tr>
                    <tr><td>Status Siswa</td><td>{{ $payload['siswa']['status'] ?? '-' }}</td></tr>
                </table>
            </section>
            <section class="panel">
                <h4>Administrasi Rapot</h4>
                <table class="meta-table">
                    <tr><td>Periode</td><td>{{ $payload['rapot']['periode_tahun'] ?? '-' }}</td></tr>
                    <tr><td>Semester</td><td>{{ $payload['rapot']['semester'] ?? '-' }}</td></tr>
                    <tr><td>Tanggal Rapot</td><td>{{ $payload['rapot']['tanggal_rapot'] ?? '-' }}</td></tr>
                    <tr><td>Predikat Boarding</td><td>{{ $payload['rapot']['predikat_boarding'] ?? '-' }}</td></tr>
                    <tr><td>Sinkron Terakhir</td><td>{{ $rapot->generated_at?->translatedFormat('d M Y H:i') ?? 'Belum ada' }}</td></tr>
                </table>
            </section>
        </div>

        <div class="stats">
            <div class="stat">
                <small>Surat</small>
                <strong>{{ $payload['pencapaian']['realisasi']['surat'] ?? 0 }}</strong>
                <span class="muted">Target {{ $payload['pencapaian']['target']['surat'] ?? 0 }}</span>
            </div>
            <div class="stat">
                <small>Doa</small>
                <strong>{{ $payload['pencapaian']['realisasi']['doa'] ?? 0 }}</strong>
                <span class="muted">Target {{ $payload['pencapaian']['target']['doa'] ?? 0 }}</span>
            </div>
            <div class="stat">
                <small>Hadits</small>
                <strong>{{ $payload['pencapaian']['realisasi']['hadits'] ?? 0 }}</strong>
                <span class="muted">Target {{ $payload['pencapaian']['target']['hadits'] ?? 0 }}</span>
            </div>
            <div class="stat">
                <small>Status</small>
                <strong style="font-size: 18px;">{{ $payload['pencapaian']['status'] ?? '-' }}</strong>
                <span class="muted">Rekap target boarding</span>
            </div>
        </div>

        <section class="section">
            <h4 class="section-title">Rekap Pencapaian Utama</h4>
            <div class="two-col">
                <div class="summary-box"><strong>Quran Tuntas</strong>
{{ $payload['pencapaian']['surat_quran_tuntas'] ?? '-' }}</div>
                <div class="summary-box"><strong>Hadits Tuntas</strong>
{{ $payload['pencapaian']['hadits_tuntas'] ?? '-' }}</div>
                <div class="summary-box"><strong>Hafalan Surat & Doa</strong>
Surat:
{{ $payload['pencapaian']['hafalan_surat'] ?? '-' }}

Doa:
{{ $payload['pencapaian']['hafalan_doa'] ?? '-' }}</div>
                <div class="summary-box"><strong>Hafalan Lainnya</strong>
{{ $payload['pencapaian']['hafalan_lainnya'] ?? '-' }}</div>
            </div>
        </section>

        <section class="section">
            <h4 class="section-title">Detail Target Boarding</h4>
            @forelse(($payload['pencapaian']['detail_kelompok'] ?? []) as $group)
                <div class="group-title">{{ $group['judul'] ?? '-' }}</div>
                <div style="overflow-x:auto;">
                    <table class="grid">
                        <thead>
                            <tr>
                                <th>Nama Target</th>
                                <th>Target</th>
                                <th>Capaian</th>
                                <th>Satuan</th>
                                <th>Status</th>
                                <th>Tuntas</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($group['rows'] ?? []) as $row)
                                <tr>
                                    <td>{{ $row['nama_target'] ?? '-' }}</td>
                                    <td>{{ $row['target_nilai'] ?? 0 }}</td>
                                    <td>{{ $row['capaian_nilai'] ?? 0 }}</td>
                                    <td>{{ $row['satuan'] ?? '-' }}</td>
                                    <td>{{ $row['status_detail'] ?? '-' }}</td>
                                    <td>{{ $row['tuntas_at'] ?? '-' }}</td>
                                    <td>{{ $row['detail'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <div class="summary-box">Belum ada detail target boarding yang tercatat untuk murid ini.</div>
            @endforelse
        </section>

        <section class="section">
            <h4 class="section-title">Catatan Pamong dan Tindak Lanjut</h4>
            <div class="two-col">
                <div class="summary-box"><strong>Ringkasan Pencapaian</strong>
{{ $rapot->ringkasan_pencapaian ?: '-' }}</div>
                <div class="summary-box"><strong>Catatan Pamong</strong>
{{ $rapot->catatan_pamong ?: '-' }}</div>
            </div>
            <div class="summary-box" style="margin-top: 12px;"><strong>Rekomendasi Tindak Lanjut</strong>
{{ $rapot->rekomendasi_tindak_lanjut ?: '-' }}</div>
            <div class="summary-box" style="margin-top: 12px;"><strong>Target Berikutnya</strong>
{{ $payload['pencapaian']['target_berikutnya'] ?? '-' }}</div>
        </section>

        <section class="section">
            <h4 class="section-title">Rekap Keuangan Kas</h4>
            <div style="overflow-x:auto;">
                <table class="grid">
                    <thead>
                        <tr>
                            <th>Pamong</th>
                            <th>Kategori Asrama</th>
                            <th>Titipan Masuk</th>
                            <th>Pemberian Uang Saku</th>
                            <th>Setoran Kas (Qurban + Isrun)</th>
                            <th>Sisa di Pamong</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $keuangan = $payload['keuangan'] ?? [];
                            $totalTitipan = (int) ($keuangan['total_titipan'] ?? $keuangan['titipan_masuk'] ?? 0);
                            $totalPemberian = (int) ($keuangan['total_pemberian'] ?? $keuangan['pemberian_uang_saku'] ?? 0);
                            $totalKas = (int) ($keuangan['total_kas'] ?? $keuangan['setoran_kas'] ?? 0);
                            $saldoTersisa = (int) ($keuangan['saldo_tersisa'] ?? 0);
                        @endphp
                        <tr>
                            <td>{{ $payload['keuangan']['pamong_nama'] ?? '-' }}</td>
                            <td>{{ $payload['keuangan']['kategori_asrama'] ?? '-' }}</td>
                            <td>{{ \App\Models\BoardingKeuanganSiswa::formatRupiah($totalTitipan) }}</td>
                            <td>{{ \App\Models\BoardingKeuanganSiswa::formatRupiah($totalPemberian) }}</td>
                            <td>{{ \App\Models\BoardingKeuanganSiswa::formatRupiah($totalKas) }}</td>
                            <td>{{ \App\Models\BoardingKeuanganSiswa::formatRupiah($saldoTersisa) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="section">
            <h4 class="section-title">Riwayat Konseling Terbaru</h4>
            <div style="overflow-x:auto;">
                <table class="grid">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Kategori</th>
                            <th>Prioritas</th>
                            <th>Status</th>
                            <th>Ringkasan</th>
                            <th>Tindak Lanjut</th>
                            <th>Konselor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($payload['konseling'] ?? []) as $row)
                            <tr>
                                <td>{{ $row['tanggal'] ?? '-' }}</td>
                                <td>{{ $row['kategori'] ?? '-' }}</td>
                                <td>{{ $row['prioritas'] ?? '-' }}</td>
                                <td>{{ $row['status_tindak_lanjut'] ?? '-' }}</td>
                                <td>{{ $row['ringkasan_masalah'] ?? '-' }}</td>
                                <td>{{ $row['tindak_lanjut'] ?? '-' }}</td>
                                <td>{{ $row['konselor'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="center">Belum ada data konseling untuk murid ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="section">
            <h4 class="section-title">Pengesahan</h4>
            <p class="muted">{{ $payload['school']['kota'] ?? ($rapot->tempat_cetak ?? '-') }}, {{ $payload['rapot']['tanggal_rapot'] ?? '-' }}</p>
            <div class="signature-grid">
                <div class="signature-box">
                    <div>Wali Pamong</div>
                    <div class="name">{{ $payload['signatures']['wali_pamong_nama'] ?? '-' }}</div>
                </div>
                <div class="signature-box">
                    <div>Kepala Boarding</div>
                    <div class="name">{{ $payload['signatures']['kepala_boarding_nama'] ?? '-' }}</div>
                </div>
                <div class="signature-box">
                    <div>Mudir Asrama</div>
                    <div class="name">{{ $payload['signatures']['mudir_asrama_nama'] ?? '-' }}</div>
                </div>
            </div>
        </section>
    </div>

    @if($printMode)
        <script>
            window.addEventListener('load', () => window.print(), { once: true });
        </script>
    @endif
</body>
</html>
