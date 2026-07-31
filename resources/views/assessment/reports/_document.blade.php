@php
    $school = is_array(data_get($snapshot, 'school')) ? data_get($snapshot, 'school') : [];
    $period = is_array(data_get($snapshot, 'period')) ? data_get($snapshot, 'period') : [];
    $student = is_array(data_get($snapshot, 'student')) ? data_get($snapshot, 'student') : [];
    $homeroom = is_array(data_get($snapshot, 'homeroom')) ? data_get($snapshot, 'homeroom') : [];
    $subjects = data_get($snapshot, 'subjects', data_get($snapshot, 'subject_results', []));
    $subjects = is_array($subjects) ? $subjects : [];
    $signatures = data_get($snapshot, 'signatures', []);
    $signatures = is_array($signatures) ? $signatures : [];
    $logo = trim((string) data_get($school, 'logo_data_uri'));
    $logoIsSafe = preg_match('#^data:image/(?:png|jpeg|webp);base64,#i', $logo) === 1;
    $title = trim((string) data_get($templateSettings, 'report_title', data_get($templateSettings, 'title')));
    $title = $title !== '' ? $title : ($reportKind === 'ASAS' ? 'LAPORAN HASIL ASESMEN AKHIR SEMESTER' : 'LAPORAN HASIL ASESMEN TENGAH SEMESTER');
    $scoreLabel = trim((string) data_get($templateSettings, 'score_label', 'Nilai'));
    $predicateLabel = trim((string) data_get($templateSettings, 'predicate_label', 'Predikat'));
    $showPredicate = (bool) data_get($templateSettings, 'show_predicate', true);
    $showDescription = (bool) data_get($templateSettings, 'show_description', true);
    $watermark = trim((string) data_get($templateSettings, 'watermark_data_uri'));
    $watermarkSafe = preg_match('#^data:image/png;base64,#i', $watermark) === 1;
    $watermarkOpacity = min(25, max(5, (int) data_get($templateSettings, 'watermark_opacity', 10))) / 100;
    $scoreColumnCount = 3 + (int) $showPredicate + (int) $showDescription;
    $extracurricular = data_get($homeroom, 'extracurricular', data_get($homeroom, 'extracurricular_data', []));
    $achievements = data_get($homeroom, 'achievements', data_get($homeroom, 'achievement_data', []));
    $extracurricular = is_array($extracurricular) ? $extracurricular : [];
    $achievements = is_array($achievements) ? $achievements : [];
@endphp

<section class="report-page">
    @if ((bool) data_get($snapshot, 'meta.preview', false))
        <div class="report-preview-label">
            {{ (bool) data_get($snapshot, 'meta.preview_data_incomplete', false) ? 'PRATINJAU — DATA BELUM LENGKAP' : 'PRATINJAU — BUKAN RAPOR RESMI' }}
        </div>
    @endif
    @if ($watermarkSafe && (bool) data_get($templateSettings, 'watermark_enabled', false))
        <div class="report-watermark" style="opacity: {{ $watermarkOpacity }}">
            <img src="{{ $watermark }}" alt="">
        </div>
    @endif
    <table class="letterhead">
        <tr>
            <td class="letterhead__logo">
                @if ($logoIsSafe)
                    <img src="{{ $logo }}" alt="">
                @endif
            </td>
            <td class="letterhead__school">
                <p class="letterhead__school-name">{{ data_get($school, 'name', 'SMA AFBS') }}</p>
                @if (filled(data_get($school, 'address')))
                    <p class="letterhead__school-info">{{ data_get($school, 'address') }}</p>
                @endif
                @if (filled(data_get($school, 'contact')))
                    <p class="letterhead__school-info">{{ data_get($school, 'contact') }}</p>
                @endif
            </td>
            <td class="letterhead__logo"></td>
        </tr>
    </table>
    <hr class="letterhead-rule">

    <h1 class="report-title">{{ $title }}</h1>
    <p class="report-subtitle">
        Tahun Pelajaran {{ data_get($period, 'academic_year', '-') }}
        &middot; Semester {{ data_get($period, 'semester', '-') }}
    </p>

    <table class="identity">
        <tr>
            <td class="identity__label">Nama Siswa</td>
            <td class="identity__separator">:</td>
            <td>{{ data_get($student, 'name', '-') }}</td>
            <td class="identity__label">Kelas</td>
            <td class="identity__separator">:</td>
            <td>{{ data_get($student, 'class_name', '-') }}</td>
        </tr>
        <tr>
            <td class="identity__label">NIS / NISN</td>
            <td class="identity__separator">:</td>
            <td>{{ data_get($student, 'nis', '-') }} / {{ data_get($student, 'nisn', '-') }}</td>
            <td class="identity__label">Jenis Laporan</td>
            <td class="identity__separator">:</td>
            <td>{{ $reportKind }}</td>
        </tr>
    </table>

    <table class="scores">
        <thead>
            <tr>
                <th class="scores__number">No.</th>
                <th>Mata Pelajaran</th>
                <th class="scores__score">{{ $scoreLabel !== '' ? $scoreLabel : 'Nilai' }}</th>
                @if ($showPredicate)
                    <th class="scores__predicate">{{ $predicateLabel !== '' ? $predicateLabel : 'Predikat' }}</th>
                @endif
                @if ($showDescription)
                    <th>Capaian Kompetensi</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($subjects as $index => $subject)
                <tr>
                    <td class="scores__number">{{ $index + 1 }}</td>
                    <td>{{ data_get($subject, 'name', data_get($subject, 'subject_name', '-')) }}</td>
                    <td class="scores__score">{{ \App\Support\Assessment\AssessmentNumberFormatter::score(data_get($subject, 'final_score', data_get($subject, 'score'))) }}</td>
                    @if ($showPredicate)
                        <td class="scores__predicate">{{ data_get($subject, 'predicate', '-') }}</td>
                    @endif
                    @if ($showDescription)
                        <td class="scores__description">{{ data_get($subject, 'description', '-') }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td class="empty-row" colspan="{{ $scoreColumnCount }}">Belum ada hasil mata pelajaran pada snapshot ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="section-title">Ketidakhadiran</p>
    <table class="summary-table">
        <tr>
            <th>Sakit</th>
            <td>{{ (int) data_get($homeroom, 'sick_days', 0) }} hari</td>
            <th>Izin</th>
            <td>{{ (int) data_get($homeroom, 'permission_days', 0) }} hari</td>
            <th>Tanpa Keterangan</th>
            <td>{{ (int) data_get($homeroom, 'absent_days', 0) }} hari</td>
        </tr>
    </table>

    @if ($reportKind === 'ASAS')
        <p class="section-title">Ekstrakurikuler dan Prestasi</p>
        <table class="summary-table">
            <tr>
                <th>Ekstrakurikuler</th>
                <td>
                    {{ collect($extracurricular)->map(fn ($item) => is_array($item) ? (data_get($item, 'name', '-').' — '.data_get($item, 'description', data_get($item, 'grade', '-'))) : (string) $item)->implode('; ') ?: '-' }}
                </td>
            </tr>
            <tr>
                <th>Prestasi</th>
                <td>
                    {{ collect($achievements)->map(fn ($item) => is_array($item) ? (data_get($item, 'name', '-').' — '.data_get($item, 'description', data_get($item, 'level', '-'))) : (string) $item)->implode('; ') ?: '-' }}
                </td>
            </tr>
            @if (filled(data_get($homeroom, 'promotion_status')))
                <tr>
                    <th>Status Semester</th>
                    <td>{{ data_get($homeroom, 'promotion_status') }}</td>
                </tr>
            @endif
        </table>
    @endif

    <p class="section-title">Catatan Wali Kelas</p>
    <table class="summary-table">
        <tr>
            <td>{{ data_get($homeroom, 'note', data_get($homeroom, 'homeroom_note', '-')) ?: '-' }}</td>
        </tr>
    </table>

    <table class="signatures">
        <tr>
            @foreach ($signatures as $signature)
                <td>
                    <div>{{ data_get($signature, 'place_date', data_get($period, 'report_date', '')) }}</div>
                    <div>{{ data_get($signature, 'label', 'Mengetahui') }}</div>
                    <div class="signature-space"></div>
                    <div class="signature-name">{{ data_get($signature, 'name', '-') }}</div>
                    @if (filled(data_get($signature, 'identifier')))
                        <div>{{ data_get($signature, 'identifier') }}</div>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>

    <div class="footer-note">
        Dokumen snapshot revisi {{ data_get($snapshot, 'meta.revision', '-') }}
        &middot; Template v{{ data_get($snapshot, 'template.version', '-') }}
    </div>
</section>
