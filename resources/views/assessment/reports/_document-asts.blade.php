@php
    $school = is_array(data_get($snapshot, 'school')) ? data_get($snapshot, 'school') : [];
    $period = is_array(data_get($snapshot, 'period')) ? data_get($snapshot, 'period') : [];
    $student = is_array(data_get($snapshot, 'student')) ? data_get($snapshot, 'student') : [];
    $homeroom = is_array(data_get($snapshot, 'homeroom')) ? data_get($snapshot, 'homeroom') : [];
    $subjects = data_get($snapshot, 'subjects', data_get($snapshot, 'subject_results', []));
    $subjects = is_array($subjects) ? $subjects : [];
    $signatures = is_array(data_get($snapshot, 'signatures')) ? data_get($snapshot, 'signatures') : [];
    $logo = trim((string) data_get($school, 'logo_data_uri'));
    $logoIsSafe = preg_match('#^data:image/(?:png|jpeg|webp);base64,#i', $logo) === 1;
    $academicYear = data_get($period, 'academic_year', 'Demo 2025/2026');
    $className = (string) data_get($student, 'class_name', '');
    $usesChoiceGroups = preg_match('/(?:^|\s)(?:XI|XII|11|12)(?:\s|$)/i', $className) === 1;
    $isChoiceSubject = static function (mixed $subject): bool {
        $group = strtolower(trim((string) data_get($subject, 'group_code', '').' '.data_get($subject, 'group_name', '')));

        return str_contains($group, 'pilihan') || preg_match('/(?:^|\s)p(?:\s|$)/', $group) === 1;
    };
    $subjectGroups = $usesChoiceGroups
        ? ['Kelompok Umum' => array_values(array_filter($subjects, fn ($subject) => ! $isChoiceSubject($subject))), 'Kelompok Pilihan' => array_values(array_filter($subjects, $isChoiceSubject))]
        : ['Kelompok Umum' => $subjects];
    $extracurricular = data_get($homeroom, 'extracurricular', data_get($homeroom, 'extracurricular_data', []));
    $extracurricular = is_array($extracurricular) ? array_slice($extracurricular, 0, 5) : [];
    $homeroomSignature = collect($signatures)->first(fn ($signature) => str_contains(strtolower((string) data_get($signature, 'label')), 'wali kelas'));
    $signatureDate = data_get($homeroomSignature, 'place_date', collect($signatures)->pluck('place_date')->filter()->first());
    $astsPredicate = static function (mixed $score): string {
        if ($score === null || $score === '' || ! is_numeric($score)) {
            return '-';
        }

        $score = (float) $score;

        return $score < 70 ? 'D' : ($score <= 75 ? 'C' : ($score <= 85 ? 'B' : 'A'));
    };
@endphp

<div class="report-footer">Dengan Teladan Menjadi Mulia &middot; Rapor ASTS SMA Al Furqon Boarding School&middot; Tahun Pelajaran {{ $academicYear }}</div>

@php($letterhead = 'assessment.reports._asts-letterhead')
<section class="report-page report-page--asts-scores">
    @include($letterhead)
    <h1 class="report-title">LAPORAN HASIL ASESMEN SUMATIF TENGAH SEMESTER (ASTS)</h1>
    <p class="report-subtitle">Tahun Pelajaran {{ $academicYear }} &middot; Semester {{ data_get($period, 'semester', '-') }}</p>
    <table class="identity"><tr>
        <td class="identity__label">Nama Siswa</td><td class="identity__separator">:</td><td>{{ data_get($student, 'name', '-') }}</td>
        <td class="identity__label">Kelas</td><td class="identity__separator">:</td><td>{{ $className ?: '-' }}</td>
    </tr><tr>
        <td class="identity__label">NIS / NISN</td><td class="identity__separator">:</td><td>{{ data_get($student, 'nis', '-') }} / {{ data_get($student, 'nisn', '-') }}</td>
        <td class="identity__label">Jenis Laporan</td><td class="identity__separator">:</td><td>ASTS</td>
    </tr></table>

    @foreach ($subjectGroups as $groupName => $groupSubjects)
        @if ($usesChoiceGroups || $loop->first)<p class="asts-subject-group">{{ $groupName }}</p>@endif
        <table class="scores asts-scores"><thead><tr><th class="scores__number">No.</th><th>Mata Pelajaran</th><th class="scores__score">Nilai</th><th class="scores__predicate">Predikat</th></tr></thead><tbody>
            @forelse ($groupSubjects as $index => $subject)<tr><td class="scores__number">{{ $index + 1 }}</td><td>{{ data_get($subject, 'name', data_get($subject, 'subject_name', '-')) }}@if(data_get($subject, 'online_exam_demo.label')) <span style="display:inline-block; margin-left:4px; padding:1px 4px; border:1px solid #b7791f; border-radius:3px; color:#8a5a11; font-size:7px; font-weight:bold; white-space:nowrap">{{ data_get($subject, 'online_exam_demo.label') }}</span>@endif</td><td class="scores__score">{{ \App\Support\Assessment\AssessmentNumberFormatter::scoreRapor(data_get($subject, 'final_score', data_get($subject, 'score'))) }}</td><td class="scores__predicate">{{ $astsPredicate(data_get($subject, 'final_score', data_get($subject, 'score'))) }}</td></tr>
            @empty<tr><td class="empty-row" colspan="4">Belum ada mata pelajaran pada kelompok ini.</td></tr>@endforelse
        </tbody></table>
    @endforeach

    <p class="asts-kktp-title">Tabel Interval berdasarkan KKTP</p>
    <table class="asts-kktp"><thead><tr><th>KKTP</th><th colspan="4">Predikat</th></tr><tr><th>81</th><th>D<br><span>Kurang</span></th><th>C<br><span>Cukup</span></th><th>B<br><span>Baik</span></th><th>A<br><span>Sangat Baik</span></th></tr></thead><tbody><tr><td>Interval Nilai</td><td>&lt;70</td><td>70-75</td><td>76-85</td><td>86-100</td></tr></tbody></table>
</section>

<div class="report-page-break"></div>

<section class="report-page report-page--asts-summary">
    @include($letterhead)
    <h1 class="report-title">LAPORAN HASIL ASESMEN SUMATIF TENGAH SEMESTER (ASTS)</h1>
    <table class="identity asts-summary-identity"><tr>
        <td class="identity__label">Nama Siswa</td><td class="identity__separator">:</td><td>{{ data_get($student, 'name', '-') }}</td>
        <td class="identity__label">Kelas</td><td class="identity__separator">:</td><td>{{ $className ?: '-' }}</td>
    </tr><tr>
        <td class="identity__label">NIS / NISN</td><td class="identity__separator">:</td><td>{{ data_get($student, 'nis', '-') }} / {{ data_get($student, 'nisn', '-') }}</td>
        <td class="identity__label">Jenis Laporan</td><td class="identity__separator">:</td><td>ASTS</td>
    </tr></table>
    <table class="asts-summary-grid"><tr><td>
        <p class="section-title">Ketidakhadiran</p>
        <table class="summary-table summary-table--attendance"><tr><th>Sakit</th><td><span class="attendance-value">{{ (int) data_get($homeroom, 'sick_days', 0) }}&nbsp;hari</span></td></tr><tr><th>Izin</th><td><span class="attendance-value">{{ (int) data_get($homeroom, 'permission_days', 0) }}&nbsp;hari</span></td></tr><tr><th>Tanpa Keterangan</th><td><span class="attendance-value">{{ (int) data_get($homeroom, 'absent_days', 0) }}&nbsp;hari</span></td></tr></table>
    </td><td>
        <p class="section-title">Ekstrakurikuler</p>
        <table class="summary-table asts-extracurricular"><thead><tr><th>No.</th><th>Nama Ekstrakurikuler</th><th>Predikat</th></tr></thead><tbody>@foreach (range(1, 5) as $index) @php($item = $extracurricular[$index - 1] ?? [])<tr><td>{{ $index }}</td><td>{{ data_get($item, 'name', '-') }}</td><td>{{ data_get($item, 'grade', data_get($item, 'description', data_get($item, 'level', '-'))) }}</td></tr>@endforeach</tbody></table>
    </td></tr></table>

    <table class="signatures asts-signatures"><tr><td>Orang Tua/Wali</td><td>{{ $signatureDate ?: 'Tangerang, ....................' }}<br>Wali Kelas</td></tr><tr class="signature-spaces"><td><div class="signature-space"></div></td><td><div class="signature-space"></div></td></tr><tr class="signature-names"><td><div class="signature-name signature-name--blank">(................................................)</div></td><td><div class="signature-name">{{ filled(data_get($homeroomSignature, 'name')) && data_get($homeroomSignature, 'name') !== '-' ? data_get($homeroomSignature, 'name') : '................................................' }}</div></td></tr></table>
</section>
