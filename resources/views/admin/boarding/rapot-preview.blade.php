<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Data Rapot Boarding - {{ $payload['siswa']['nama'] ?? 'Murid' }}</title>
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
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        @page {
            size: A4 portrait;
            margin: 0;
        }
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
            position: relative;
            display: grid;
            grid-template-columns: var(--letterhead-logo-size, 84px) 1fr var(--letterhead-logo-size, 84px);
            gap: 18px;
            align-items: center;
            border-bottom: 3px solid var(--ink);
            padding-bottom: 18px;
            margin-bottom: 18px;
        }
        .logo-box {
            width: var(--letterhead-logo-size, 84px);
            height: var(--letterhead-logo-size, 84px);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--paper);
            overflow: visible;
            position: relative;
            z-index: 2;
        }
        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .letterhead-copy {
            position: relative;
            z-index: 1;
            text-align: center;
        }
        .letterhead-copy h1,
        .letterhead-copy h2,
        .letterhead-copy p {
            margin: 0;
        }
        .letterhead-copy h1 {
            font-size: var(--letterhead-title-size, 24px);
            letter-spacing: 0.08em;
        }
        .letterhead-copy h2 {
            margin-top: 4px;
            font-size: var(--letterhead-subtitle-size, 17px);
            font-weight: 700;
        }
        .letterhead-copy p {
            margin-top: 7px;
            color: var(--muted);
            font-size: var(--letterhead-info-size, 13px);
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
            background: #0f172a;
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
        .sheet-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 13px;
        }
        .sheet-grid th,
        .sheet-grid td {
            border: 1px solid #000;
            padding: 4px 7px;
            text-align: center;
            vertical-align: middle;
            line-height: 1.25;
        }
        .sheet-grid th {
            background: #0097a7;
            color: #fff;
            font-weight: 700;
        }
        .sheet-grid .col-main { width: 18%; }
        .sheet-grid .col-sub { width: 22%; }
        .sheet-grid .col-info { width: 60%; }
        .sheet-grid .col-mt-materi { width: 36%; }
        .sheet-grid .col-mt-info { width: 64%; }
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
            min-height: calc(var(--signature-name-gap, 72px) + 68px);
        }
        .signature-box .name {
            margin-top: var(--signature-name-gap, 72px);
            font-weight: 700;
            text-decoration: underline;
        }
        .muted {
            color: var(--muted);
        }
        .center { text-align: center; }
        @media screen and (max-width: 768px) {
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
                width: 210mm;
            }
            .page {
                width: 210mm;
                min-height: 297mm;
                max-width: 210mm;
                box-shadow: none;
                padding: 10mm;
            }
            .letterhead {
                grid-template-columns: var(--letterhead-logo-size, 84px) 1fr var(--letterhead-logo-size, 84px);
                text-align: initial;
            }
            .logo-box {
                margin: 0;
            }
            .meta-grid,
            .two-col {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .stats {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
            .doc-strip,
            .signature-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .toolbar {
                display: none;
            }
            .section-title {
                background: #0f172a !important;
                color: #fff !important;
            }
            .sheet-grid th {
                background: #0097a7 !important;
                color: #fff !important;
            }
        }
    </style>
</head>
<body>
    @php
        $letterheadContact = ($letterhead['contact'] ?? collect([$letterhead['phone'] ?? null, $letterhead['email'] ?? null])->filter()->implode(' | ')) ?: 'Kontak sekolah belum diatur.';
        $administrasiItems = \App\Models\BoardingRapot::normalizeAdministrasiRapotItems($payload['rapot']['administrasi_items'] ?? $rapot->administrasi_rapot_items ?? []);
        $letterheadLogoSize = (float) ($letterhead['logo_size'] ?? 84);
        $letterheadTitleSize = (float) ($letterhead['site_name_font_size'] ?? 24);
        $letterheadSubtitleSize = (float) ($letterhead['subtitle_font_size'] ?? 17);
        $letterheadInfoSize = (float) ($letterhead['info_font_size'] ?? 13);
        $signatureNameGap = (float) ($letterhead['signature_name_gap'] ?? 72);
    @endphp

    <div class="page">
        <div class="toolbar">
            @unless($printMode)
                <a class="button secondary" href="{{ route('admin.boarding-rapots.preview', $rapot) }}" target="_blank" rel="noreferrer">Preview Rapot</a>
                <a class="button secondary" href="{{ route('admin.boarding-rapots.export', $rapot) }}" target="_blank" rel="noreferrer">Export Excel</a>
                <a class="button secondary" href="{{ route('admin.boarding-rapots.print', $rapot) }}" target="_blank" rel="noreferrer">Mode Cetak</a>
            @endunless
            <button class="button" type="button" onclick="window.print()">Print / Simpan PDF</button>
        </div>

        <header class="letterhead" style="--letterhead-logo-size: {{ $letterheadLogoSize }}px; --letterhead-title-size: {{ $letterheadTitleSize }}px; --letterhead-subtitle-size: {{ $letterheadSubtitleSize }}px; --letterhead-info-size: {{ $letterheadInfoSize }}px;">
            <div class="logo-box">
                @if(!empty($letterhead['logo_src']))
                    <img src="{{ $letterhead['logo_src'] }}" alt="Logo sekolah">
                @endif
            </div>
            <div class="letterhead-copy">
                <h1>{{ strtoupper((string) ($letterhead['site_name'] ?? $payload['school']['nama'] ?? 'SMA AFBS')) }}</h1>
                <h2>{{ strtoupper((string) ($letterhead['subtitle'] ?? $payload['school']['boarding_label'] ?? 'BOARDING SCHOOL')) }}</h2>
                <p>{{ $letterhead['address'] ?? $payload['school']['alamat'] ?? 'Alamat sekolah belum diatur.' }}</p>
                <p>
                    {{ $letterheadContact }}
                </p>
            </div>
            <div class="logo-box">
                @if(!empty($letterhead['right_logo_src']))
                    <img src="{{ $letterhead['right_logo_src'] }}" alt="Logo yayasan">
                @endif
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
            <h3>REKAP DATA RAPOT BOARDING</h3>
            <p>Track record lengkap sumber data rapot. Halaman ini untuk pemeriksaan internal, bukan format rapot final.</p>
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
                    <tr><td>Kelas Boarding</td><td>{{ $payload['rapot']['kelas_boarding'] ?? '-' }}</td></tr>
                    <tr><td>Sinkron Terakhir</td><td>{{ $rapot->generated_at?->translatedFormat('d M Y H:i') ?? 'Belum ada' }}</td></tr>
                    @foreach($administrasiItems as $item)
                        <tr><td>{{ $item['question'] }}</td><td>{!! nl2br(e($item['answer'])) !!}</td></tr>
                    @endforeach
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

        <section class="section" style="--signature-name-gap: {{ $signatureNameGap }}px;">
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

        @php
            $hafalanGroups = $payload['pencapaian']['hafalan_detail'] ?? [];
            $makna = $payload['pencapaian']['makna'] ?? [];
            $materiBoarding = $payload['pencapaian']['materi_boarding'] ?? [];
            $mt = $payload['pencapaian']['mt'] ?? [];
            $bacaan = $payload['pencapaian']['bacaan'] ?? [];
            $activeMateriScope = \App\Models\BoardingPencapaian::normalizeMateriRapotScope($payload['pencapaian']['materi_rapot_scope'] ?? null);
            $activeMateriLabel = \App\Models\BoardingPencapaian::materiRapotScopeLabel($activeMateriScope);
            $materiBoardingSheetRows = \App\Support\Boarding\BoardingRapotSheetRows::materiBoardingRows($payload);
            $mtSheetRows = \App\Support\Boarding\BoardingRapotSheetRows::mtRows($payload);
            $materiBoardingCatatanGroup = collect($materiBoarding['manual_groups'] ?? [])->firstWhere('group', 'catatan_saran');
            $mtCatatanGroup = collect($mt['groups'] ?? [])->firstWhere('group', 'catatan_saran');
            $materiBoardingCatatanRows = is_array($materiBoardingCatatanGroup) ? ($materiBoardingCatatanGroup['rows'] ?? []) : [];
            $mtCatatanRows = is_array($mtCatatanGroup) ? ($mtCatatanGroup['rows'] ?? []) : [];
        @endphp

        <section class="section">
            <h4 class="section-title">Detail {{ $activeMateriLabel }}</h4>

            <div class="summary-box">
                <strong>Target Materi Rapot Aktif</strong>
{{ $activeMateriLabel }}

@if($activeMateriScope === \App\Models\BoardingPencapaian::MATERI_RAPOT_SCOPE_BOARDING)
                <strong>Makna</strong>
{{ $makna['summary_label'] ?? 'Belum ada progres makna.' }}

                <strong>Bacaan</strong>
{{ $bacaan['summary_label'] ?? 'Belum ada riwayat bacaan.' }}
@else
                <strong>Materi MT</strong>
{{ $mt['summary_label'] ?? 'Belum ada progres materi MT.' }}
@endif
            </div>

            @if($activeMateriScope === \App\Models\BoardingPencapaian::MATERI_RAPOT_SCOPE_BOARDING)
            <div class="group-title">Pencapaian Target Materi Boarding</div>
            <div style="overflow-x:auto;">
                <table class="sheet-grid">
                    <colgroup>
                        <col class="col-main">
                        <col class="col-sub">
                        <col class="col-info">
                    </colgroup>
                    <thead>
                        <tr>
                            <th colspan="2">Materi</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td rowspan="2">{{ $materiBoardingSheetRows[1][0] }}</td>
                            <td>{{ $materiBoardingSheetRows[1][1] }}</td>
                            <td>{{ $materiBoardingSheetRows[1][2] }}</td>
                        </tr>
                        <tr>
                            <td>{{ $materiBoardingSheetRows[2][1] }}</td>
                            <td>{{ $materiBoardingSheetRows[2][2] }}</td>
                        </tr>
                        <tr>
                            <td>{{ $materiBoardingSheetRows[3][0] }}</td>
                            <td>{{ $materiBoardingSheetRows[3][1] }}</td>
                            <td>{{ $materiBoardingSheetRows[3][2] }}</td>
                        </tr>
                        <tr>
                            <td colspan="2">{{ $materiBoardingSheetRows[4][0] }}</td>
                            <td>{{ $materiBoardingSheetRows[4][2] }}</td>
                        </tr>
                        <tr>
                            <td rowspan="4">{{ $materiBoardingSheetRows[5][0] }}</td>
                            <td>{{ $materiBoardingSheetRows[5][1] }}</td>
                            <td>{{ $materiBoardingSheetRows[5][2] }}</td>
                        </tr>
                        @foreach(array_slice($materiBoardingSheetRows, 6, 3) as $row)
                            <tr>
                                <td>{{ $row[1] }}</td>
                                <td>{{ $row[2] }}</td>
                            </tr>
                        @endforeach
                        @foreach(array_slice($materiBoardingSheetRows, 9, 4) as $row)
                            <tr>
                                <td colspan="2">{{ $row[0] }}</td>
                                <td>{{ $row[2] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="group-title">Pencapaian Target Materi MT</div>
            <div style="overflow-x:auto;">
                <table class="sheet-grid">
                    <colgroup>
                        <col class="col-mt-materi">
                        <col class="col-mt-info">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Materi</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(array_slice($mtSheetRows, 1) as $row)
                            <tr>
                                <td>{{ $row[0] }}</td>
                                <td>{{ $row[1] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <div class="group-title">Catatan dan Saran {{ $activeMateriLabel }}</div>
            @if($activeMateriScope === \App\Models\BoardingPencapaian::MATERI_RAPOT_SCOPE_BOARDING)
            <div>
                <div class="summary-box"><strong>Materi Boarding</strong><br>
                    @forelse($materiBoardingCatatanRows as $row)
                        {{ $row['target_name'] ?? '-' }}: {{ $row['grade'] ?? '-' }}@if(filled($row['notes'] ?? null)) - {{ $row['notes'] }}@endif<br>
                    @empty
                        Belum ada Catatan dan Saran Materi Boarding yang tersinkron.
                    @endforelse
                </div>
            </div>
            @else
            <div>
                <div class="summary-box"><strong>Materi MT</strong><br>
                    @forelse($mtCatatanRows as $row)
                        {{ $row['target_name'] ?? '-' }}: {{ $row['grade'] ?? $row['capaian'] ?? '-' }}@if(filled($row['notes'] ?? null)) - {{ $row['notes'] }}@endif<br>
                    @empty
                        Belum ada Catatan dan Saran Materi MT yang tersinkron.
                    @endforelse
                </div>
            </div>
            @endif

            @if($activeMateriScope === \App\Models\BoardingPencapaian::MATERI_RAPOT_SCOPE_BOARDING)
            @foreach($hafalanGroups as $group)
                <div class="group-title">Hafalan - {{ $group['judul'] ?? '-' }}</div>
                <div style="overflow-x:auto;">
                    <table class="grid">
                        <thead>
                            <tr>
                                <th>Hafalan</th>
                                <th>Jenis</th>
                                <th>Nilai</th>
                                <th>Tanggal</th>
                                <th>Penyimak</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($group['rows'] ?? []) as $row)
                                <tr>
                                    <td>{{ $row['nama_point'] ?? '-' }}</td>
                                    <td>{{ $row['jenis'] ?? '-' }}</td>
                                    <td>{{ $row['score'] ?? '-' }}</td>
                                    <td>{{ $row['assessed_at'] ?? '-' }}</td>
                                    <td>{{ $row['reviewer'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach

            @foreach(($makna['groups'] ?? []) as $group)
                <div class="group-title">{{ $group['judul'] ?? '-' }}</div>
                <div style="overflow-x:auto;">
                    <table class="grid">
                        <thead>
                            <tr>
                                <th>Target Makna</th>
                                <th>Status</th>
                                <th>Kurang</th>
                                <th>Update</th>
                                <th>Diupdate Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($group['rows'] ?? []) as $row)
                                <tr>
                                    <td>{{ $row['target_name'] ?? '-' }}</td>
                                    <td>{{ $row['status'] ?? '-' }}</td>
                                    <td>{{ filled($row['remaining_pages'] ?? null) ? ($row['remaining_pages'].' dari '.($row['total_pages'] ?? '-').' lembar') : '-' }}</td>
                                    <td>{{ $row['updated_at'] ?? '-' }}</td>
                                    <td>{{ $row['updated_by'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach

            <div class="group-title">Rekap Materi Boarding</div>
            <div class="summary-box">
                <strong>Ringkasan</strong>
{{ $materiBoarding['summary_label'] ?? 'Belum ada progres materi boarding.' }}

                <strong>Bacaan Qur'an</strong>
{{ $materiBoarding['bacaan_quran']['summary_label'] ?? '-' }}

                <strong>Makna Qur'an</strong>
{{ $materiBoarding['makna_quran']['summary_label'] ?? '-' }}

                <strong>Makna Hadits</strong>
{{ $materiBoarding['makna_hadits']['summary_label'] ?? '-' }}
            </div>

            @if(($materiBoarding['hafalan'] ?? []) !== [])
                <div style="overflow-x:auto;">
                    <table class="grid">
                        <thead>
                            <tr>
                                <th>Kelas Hafalan</th>
                                <th>Nilai</th>
                                <th>Dinilai</th>
                                <th>Total Materi</th>
                                <th>Rata-rata</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($materiBoarding['hafalan'] ?? []) as $row)
                                <tr>
                                    <td>{{ $row['judul'] ?? '-' }}</td>
                                    <td>{{ $row['grade_label'] ?? '-' }}</td>
                                    <td>{{ $row['assessed'] ?? 0 }}</td>
                                    <td>{{ $row['total'] ?? 0 }}</td>
                                    <td>{{ $row['average'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @foreach(($materiBoarding['manual_groups'] ?? []) as $group)
                <div class="group-title">{{ $group['judul'] ?? '-' }}</div>
                <div style="overflow-x:auto;">
                    <table class="grid">
                        <thead>
                            <tr>
                                <th>Materi</th>
                                <th>Nilai</th>
                                <th>Keterangan</th>
                                <th>Update</th>
                                <th>Diupdate Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($group['rows'] ?? []) as $row)
                                <tr>
                                    <td>{{ $row['target_name'] ?? '-' }}</td>
                                    <td>{{ $row['grade'] ?? '-' }}</td>
                                    <td>{{ $row['notes'] ?? '-' }}</td>
                                    <td>{{ $row['updated_at'] ?? '-' }}</td>
                                    <td>{{ $row['updated_by'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
            @endif

            @if($activeMateriScope === \App\Models\BoardingPencapaian::MATERI_RAPOT_SCOPE_MT)
            @foreach(($mt['groups'] ?? []) as $group)
                <div class="group-title">{{ $group['judul'] ?? '-' }}</div>
                <div style="overflow-x:auto;">
                    <table class="grid">
                        <thead>
                            <tr>
                                <th>Materi MT</th>
                                <th>Capaian</th>
                                <th>Catatan</th>
                                <th>Update</th>
                                <th>Diupdate Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($group['rows'] ?? []) as $row)
                                <tr>
                                    <td>{{ $row['target_name'] ?? '-' }}</td>
                                    <td>{{ $row['capaian'] ?? '-' }}</td>
                                    <td>{{ $row['notes'] ?? '-' }}</td>
                                    <td>{{ $row['updated_at'] ?? '-' }}</td>
                                    <td>{{ $row['updated_by'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
            @endif

            @if($activeMateriScope === \App\Models\BoardingPencapaian::MATERI_RAPOT_SCOPE_BOARDING)
            @if(($bacaan['rows'] ?? []) !== [])
                <div class="group-title">Riwayat Bacaan</div>
                <div style="overflow-x:auto;">
                    <table class="grid">
                        <thead>
                            <tr>
                                <th>Tanggal Baca</th>
                                <th>Nilai</th>
                                <th>Penyimak</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($bacaan['rows'] ?? []) as $row)
                                <tr>
                                    <td>{{ $row['tanggal'] ?? '-' }}</td>
                                    <td>{{ $row['nilai'] ?? '-' }}</td>
                                    <td>{{ $row['reviewer'] ?? '-' }}</td>
                                    <td>{{ $row['notes'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @endif
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
                    <div>{{ $payload['signatures']['wali_pamong_label'] ?? 'Wali Pamong' }}</div>
                    <div class="name">{{ $payload['signatures']['wali_pamong_nama'] ?? '-' }}</div>
                </div>
                <div class="signature-box">
                    <div>{{ $payload['signatures']['kepala_boarding_label'] ?? 'Kepala Boarding' }}</div>
                    <div class="name">{{ $payload['signatures']['kepala_boarding_nama'] ?? '-' }}</div>
                </div>
                <div class="signature-box">
                    <div>{{ $payload['signatures']['mudir_asrama_label'] ?? 'Mudir Asrama' }}</div>
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
