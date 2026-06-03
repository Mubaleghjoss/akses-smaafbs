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
            --line: #0f172a;
            --soft-line: #cbd5e1;
            --soft: #f8fafc;
            --accent: #0097a7;
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
            font-size: 11.5px;
            line-height: 1.35;
            padding: 18px;
        }
        .page {
            width: 100%;
            max-width: 210mm;
            margin: 0 auto;
            background: var(--paper);
            padding: 10mm;
            box-shadow: 0 18px 48px rgba(15, 23, 42, 0.10);
        }
        .toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 8px 13px;
            border-radius: 999px;
            text-decoration: none;
            background: var(--ink);
            color: #fff;
            font-size: 12px;
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
            grid-template-columns: var(--letterhead-logo-size, 58px) 1fr var(--letterhead-logo-size, 58px);
            gap: 14px;
            align-items: center;
            border-bottom: 2px solid var(--ink);
            padding-bottom: 9px;
            margin-bottom: 8px;
        }
        .logo-box {
            width: var(--letterhead-logo-size, 58px);
            height: var(--letterhead-logo-size, 58px);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: visible;
            position: relative;
            z-index: 2;
            background: transparent;
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
            font-size: var(--letterhead-title-size, 18px);
            letter-spacing: 0.05em;
        }
        .letterhead-copy h2 {
            margin-top: 2px;
            font-size: var(--letterhead-subtitle-size, 13px);
            font-weight: 700;
        }
        .letterhead-copy p {
            margin-top: 3px;
            color: var(--muted);
            font-size: var(--letterhead-info-size, 10.5px);
        }
        .title-block {
            text-align: center;
            margin: 8px 0 10px;
        }
        .title-block h3 {
            margin: 0;
            font-size: 17px;
            letter-spacing: 0.04em;
        }
        .title-block p {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 10.5px;
        }
        .intro-text {
            margin: 0 0 9px;
            text-align: justify;
            font-size: 11px;
        }
        .intro-text p {
            margin: 0 0 4px;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 9px;
            margin-bottom: 10px;
        }
        .panel {
            border: 1px solid var(--soft-line);
            padding: 8px 10px;
        }
        .panel h4 {
            margin: 0 0 5px;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 2.5px 0;
            vertical-align: top;
        }
        .meta-table td:first-child {
            width: 42%;
            color: var(--muted);
        }
        .section-title {
            margin: 0 0 6px;
            padding: 5px 8px;
            background: #0f172a;
            color: #fff;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .scope-note {
            border: 1px solid var(--soft-line);
            background: var(--soft);
            padding: 6px 8px;
            margin-bottom: 7px;
            font-size: 11px;
        }
        .sheet-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11.2px;
        }
        .sheet-grid th,
        .sheet-grid td {
            border: 1px solid #000;
            padding: 4px 5px;
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
        .signature-section {
            margin-top: 11px;
        }
        .signature-place {
            margin: 0 0 6px;
            color: var(--muted);
            font-size: 11px;
            text-align: right;
        }
        .signature-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            text-align: center;
            font-size: 11.2px;
        }
        .signature-box {
            min-height: calc(var(--signature-name-gap, 42px) + 34px);
        }
        .signature-box .name {
            margin-top: var(--signature-name-gap, 42px);
            font-weight: 700;
            text-decoration: underline;
        }
        @media screen and (max-width: 768px) {
            body { padding: 10px; }
            .page { padding: 14px; }
            .letterhead,
            .meta-grid,
            .signature-grid {
                grid-template-columns: 1fr;
            }
            .letterhead {
                text-align: center;
            }
            .logo-box {
                margin: 0 auto;
            }
            .sheet-scroll {
                overflow-x: auto;
            }
            .sheet-grid {
                min-width: 640px;
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
                grid-template-columns: var(--letterhead-logo-size, 58px) 1fr var(--letterhead-logo-size, 58px);
                text-align: initial;
            }
            .logo-box {
                margin: 0;
            }
            .meta-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .signature-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .sheet-scroll {
                overflow: visible;
            }
            .toolbar {
                display: none;
            }
            .sheet-grid {
                font-size: 10.5px;
                min-width: 0;
                width: 100%;
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
        $activeMateriScope = \App\Models\BoardingPencapaian::normalizeMateriRapotScope($payload['pencapaian']['materi_rapot_scope'] ?? null);
        $activeMateriLabel = \App\Models\BoardingPencapaian::materiRapotScopeLabel($activeMateriScope);
        $materiBoardingSheetRows = \App\Support\Boarding\BoardingRapotSheetRows::materiBoardingRows($payload);
        $mtSheetRows = \App\Support\Boarding\BoardingRapotSheetRows::mtRows($payload);
        $statusLabel = \App\Models\BoardingRapot::statusOptions()[$rapot->status_rapot] ?? ($payload['rapot']['status_rapot'] ?? '-');
        $prolog = $payload['document']['prolog'] ?? \App\Models\BoardingRapot::DEFAULT_PROLOG;
        $administrasiItems = \App\Models\BoardingRapot::normalizeAdministrasiRapotItems($payload['rapot']['administrasi_items'] ?? $rapot->administrasi_rapot_items ?? []);
        $letterheadContact = ($letterhead['contact'] ?? collect([$letterhead['phone'] ?? null, $letterhead['email'] ?? null])->filter()->implode(' | ')) ?: 'Kontak sekolah belum diatur.';
        $letterheadLogoSize = (float) ($letterhead['logo_size'] ?? 58);
        $letterheadTitleSize = (float) ($letterhead['site_name_font_size'] ?? 18);
        $letterheadSubtitleSize = (float) ($letterhead['subtitle_font_size'] ?? 13);
        $letterheadInfoSize = (float) ($letterhead['info_font_size'] ?? 10.5);
        $signatureNameGap = (float) ($letterhead['signature_name_gap'] ?? 42);
    @endphp

    <div class="page">
        <div class="toolbar">
            @unless($printMode)
                <a class="button secondary" href="{{ route('admin.boarding-rapots.rekap', $rapot) }}" target="_blank" rel="noreferrer">Rekap Data</a>
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
                <p>{{ $letterheadContact }}</p>
            </div>
            <div class="logo-box">
                @if(!empty($letterhead['right_logo_src']))
                    <img src="{{ $letterhead['right_logo_src'] }}" alt="Logo yayasan">
                @endif
            </div>
        </header>

        <section class="title-block">
            <h3>RAPOT BOARDING</h3>
        </section>

        <section class="intro-text">
            {!! nl2br(e($prolog)) !!}
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
                    <tr><td>Nomor Dokumen</td><td>{{ $payload['rapot']['nomor_dokumen'] ?? '-' }}</td></tr>
                    <tr><td>Periode</td><td>{{ $payload['rapot']['periode_tahun'] ?? '-' }}</td></tr>
                    <tr><td>Semester</td><td>{{ ucfirst((string) ($payload['rapot']['semester'] ?? '-')) }}</td></tr>
                    <tr><td>Tanggal Rapot</td><td>{{ $payload['rapot']['tanggal_rapot'] ?? '-' }}</td></tr>
                    <tr><td>Status</td><td>{{ $statusLabel }}</td></tr>
                    <tr><td>Kelas Boarding</td><td>{{ $payload['rapot']['kelas_boarding'] ?? '-' }}</td></tr>
                    @foreach($administrasiItems as $item)
                        <tr><td>{{ $item['question'] }}</td><td>{!! nl2br(e($item['answer'])) !!}</td></tr>
                    @endforeach
                </table>
            </section>
        </div>

        <section>
            <h4 class="section-title">{{ $activeMateriScope === \App\Models\BoardingPencapaian::MATERI_RAPOT_SCOPE_MT ? 'Pencapaian Target Materi MT' : 'Pencapaian Target Materi Boarding' }}</h4>

            @if($activeMateriScope === \App\Models\BoardingPencapaian::MATERI_RAPOT_SCOPE_BOARDING)
                <div class="sheet-scroll">
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
                <div class="sheet-scroll">
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
        </section>

        <section class="signature-section" style="--signature-name-gap: {{ $signatureNameGap }}px;">
            <h4 class="section-title">Pengesahan</h4>
            <p class="signature-place">{{ $payload['school']['kota'] ?? ($rapot->tempat_cetak ?? '-') }}, {{ $payload['rapot']['tanggal_rapot'] ?? '-' }}</p>
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
