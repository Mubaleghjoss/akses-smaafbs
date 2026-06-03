@php
    $payloadRapot = $payload['rapot'] ?? [];
    $currentKelasAuto = $payloadRapot['kelas_boarding_auto'] ?? $payloadRapot['kelas_boarding'] ?? 'Kelas Pegon Bacaan';
@endphp

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Manual Rapot Boarding</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f8fafc;
            --panel: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --line: #d9e2ec;
            --green: #047857;
            --green-dark: #065f46;
            --danger: #b91c1c;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #020617;
                --panel: #0f172a;
                --text: #e5e7eb;
                --muted: #94a3b8;
                --line: #1f2937;
            }
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 14px/1.5 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            width: min(1120px, 100%);
            margin: 0 auto;
            padding: 18px;
        }

        .topbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        h1 {
            margin: 0;
            font-size: clamp(22px, 5vw, 32px);
            line-height: 1.15;
        }

        .subtitle {
            margin: 6px 0 0;
            color: var(--muted);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        a,
        button {
            border-radius: 999px;
            border: 1px solid var(--line);
            background: var(--panel);
            color: var(--green);
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            padding: 0 16px;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
        }

        button.primary {
            border-color: var(--green);
            background: var(--green);
            color: #ffffff;
            cursor: pointer;
        }

        .notice,
        .errors {
            border: 1px solid var(--line);
            border-radius: 10px;
            margin-bottom: 14px;
            padding: 12px 14px;
            background: var(--panel);
        }

        .notice {
            border-color: #86efac;
            color: var(--green-dark);
        }

        .errors {
            border-color: #fecaca;
            color: var(--danger);
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--panel);
            overflow: hidden;
        }

        .card h2 {
            margin: 0;
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            font-size: 16px;
        }

        .fields {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            padding: 16px;
        }

        label {
            display: grid;
            gap: 6px;
            font-weight: 700;
        }

        input,
        select,
        textarea {
            width: 100%;
            min-width: 0;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: transparent;
            color: var(--text);
            font: inherit;
            padding: 10px 12px;
        }

        textarea {
            min-height: 96px;
            resize: vertical;
        }

        .readonly {
            color: var(--muted);
            font-weight: 600;
        }

        .administrasi-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            border-top: 1px solid var(--line);
            padding-top: 12px;
        }

        .submitbar {
            position: sticky;
            bottom: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 16px;
            padding: 12px 0;
            background: color-mix(in srgb, var(--bg) 88%, transparent);
        }

        @media (min-width: 760px) {
            .grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .wide {
                grid-column: 1 / -1;
            }

            .fields.two {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .administrasi-row {
                grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
            }
        }
    </style>
</head>
<body>
<main>
    <div class="topbar">
        <div>
            <h1>Edit Manual Rapot Boarding</h1>
            <p class="subtitle">Form ini menyimpan data tanpa Livewire, jadi tidak memakai request <code>/livewire/update</code>.</p>
        </div>
        <div class="actions">
            <a href="{{ route('filament.admin.resources.boarding-rapots.index') }}">Kembali ke daftar</a>
            <a href="{{ route('admin.boarding-rapots.preview', $rapot) }}" target="_blank" rel="noopener">Preview</a>
        </div>
    </div>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="errors">
            <strong>Data belum tersimpan.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('admin.boarding-rapots.manual-update', $rapot) }}">
        @csrf

        <div class="grid">
            <section class="card">
                <h2>Identitas Murid</h2>
                <div class="fields">
                    <label>
                        Murid
                        <div class="readonly">{{ $rapot->siswa?->nama ?? '-' }}</div>
                    </label>
                    <label>
                        Rombel
                        <div class="readonly">{{ $rapot->siswa?->rombel_saat_ini ?? '-' }}</div>
                    </label>
                    <label>
                        Referensi Kelas dari Hafalan
                        <div class="readonly">{{ $currentKelasAuto }}</div>
                    </label>
                    <label>
                        Pamong Penanggung Jawab
                        <select name="pamong_user_id" required>
                            @foreach ($pamongOptions as $id => $name)
                                <option value="{{ $id }}" @selected((string) old('pamong_user_id', $rapot->pamong_user_id) === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </section>

            <section class="card">
                <h2>Data Dokumen</h2>
                <div class="fields two">
                    <label>
                        Periode Tahun
                        <input name="periode_tahun" value="{{ old('periode_tahun', $rapot->periode_tahun) }}" required maxlength="20">
                    </label>
                    <label>
                        Semester
                        <select name="semester" required>
                            @foreach ($semesterOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('semester', $rapot->semester) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Tanggal Rapot
                        <input type="date" name="tanggal_rapot" value="{{ old('tanggal_rapot', optional($rapot->tanggal_rapot)->format('Y-m-d')) }}">
                    </label>
                    <label>
                        Status Rapot
                        <select name="status_rapot" required>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('status_rapot', $rapot->status_rapot) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Nomor Dokumen
                        <input name="nomor_dokumen" value="{{ old('nomor_dokumen', $rapot->nomor_dokumen) }}" maxlength="50">
                    </label>
                    @if ($kelasOverrideColumnAvailable)
                        @php($selectedKelasBoarding = old('kelas_boarding_override', $rapot->kelas_boarding_override))
                        <label>
                            Kelas Boarding
                            <select name="kelas_boarding_override" required>
                                <option value="" disabled @selected(blank($selectedKelasBoarding))>Pilih kelas boarding</option>
                                @foreach ($boardingClassOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedKelasBoarding === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                </div>
            </section>

            <section class="card">
                <h2>Tanda Tangan</h2>
                <div class="fields">
                    <label>
                        Nama Tanda Tangan 1
                        <input name="wali_pamong_nama" value="{{ old('wali_pamong_nama', $rapot->wali_pamong_nama) }}" maxlength="100">
                    </label>
                    <label>
                        Nama Tanda Tangan 2
                        <input name="kepala_boarding_nama" value="{{ old('kepala_boarding_nama', $rapot->kepala_boarding_nama) }}" maxlength="100">
                    </label>
                    <label>
                        Nama Tanda Tangan 3
                        <input name="mudir_asrama_nama" value="{{ old('mudir_asrama_nama', $rapot->mudir_asrama_nama) }}" maxlength="100">
                    </label>
                    <label>
                        Tempat Cetak
                        <input name="tempat_cetak" value="{{ old('tempat_cetak', $rapot->tempat_cetak) }}" maxlength="100">
                    </label>
                </div>
            </section>

            <section class="card">
                <h2>Ringkasan Manual</h2>
                <div class="fields">
                    <label>
                        Ringkasan Pencapaian
                        <textarea name="ringkasan_pencapaian">{{ old('ringkasan_pencapaian', $rapot->ringkasan_pencapaian) }}</textarea>
                    </label>
                    <label>
                        Catatan Pamong
                        <textarea name="catatan_pamong">{{ old('catatan_pamong', $rapot->catatan_pamong) }}</textarea>
                    </label>
                    <label>
                        Rekomendasi Tindak Lanjut
                        <textarea name="rekomendasi_tindak_lanjut">{{ old('rekomendasi_tindak_lanjut', $rapot->rekomendasi_tindak_lanjut) }}</textarea>
                    </label>
                </div>
            </section>

            @if ($administrasiColumnAvailable)
                <section class="card wide">
                    <h2>Administrasi Rapot Manual</h2>
                    <div class="fields">
                        @foreach ($administrasiRows as $index => $row)
                            <div class="administrasi-row">
                                <label>
                                    Pertanyaan {{ $index + 1 }}
                                    <input name="administrasi_questions[]" value="{{ old('administrasi_questions.'.$index, $row['question'] ?? '') }}" maxlength="120">
                                </label>
                                <label>
                                    Jawaban {{ $index + 1 }}
                                    <textarea name="administrasi_answers[]" maxlength="500">{{ old('administrasi_answers.'.$index, $row['answer'] ?? '') }}</textarea>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <div class="submitbar">
            <a href="{{ route('filament.admin.resources.boarding-rapots.index') }}">Batal</a>
            <button class="primary" type="submit">Simpan Manual</button>
        </div>
    </form>
</main>
</body>
</html>
