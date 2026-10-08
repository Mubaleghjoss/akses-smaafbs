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
    $academicYear = preg_replace('/^\s*Tahun\s+Pelajaran\s+/iu', '', (string) data_get($period, 'academic_year', 'Demo 2025/2026')) ?: 'Demo 2025/2026';
    $className = (string) data_get($student, 'class_name', '');
    $studentNis = trim((string) data_get($student, 'nis', '')) ?: '-';
    $studentNisn = trim((string) data_get($student, 'nisn', '')) ?: '-';
    $reportTitle = trim((string) data_get($templateSettings, 'report_title')) ?: 'LAPORAN HASIL ASESMEN SUMATIF TENGAH SEMESTER (ASTS)';
    $footerText = trim((string) data_get($templateSettings, 'footer_text')) ?: 'Dengan Teladan Menjadi Mulia · Rapor ASTS '.(trim((string) data_get($school, 'name')) ?: 'SMA Al Furqon Boarding School').' · Tahun Pelajaran '.$academicYear;
    $scoreLabel = trim((string) data_get($templateSettings, 'score_label', 'Nilai')) ?: 'Nilai';
    $predicateLabel = trim((string) data_get($templateSettings, 'predicate_label', 'Predikat')) ?: 'Predikat';
    $tableSignatureSpacing = min(24, max(0, (float) data_get($templateSettings, 'report_layout.table_signature_spacing', 24)));
    $signatureSpacing = min(110, max(32, (float) data_get($templateSettings, 'report_layout.signature_spacing', 64)));
    $kopTitleSpacing = min(16, max(0, (float) data_get($templateSettings, 'report_layout.kop_title_spacing', 7)));
    $titleIdentitySpacing = min(16, max(0, (float) data_get($templateSettings, 'report_layout.title_identity_spacing', 4)));
    $usesChoiceGroups = preg_match('/(?:^|\s)(?:XI|XII|11|12)(?:\s|$)/i', $className) === 1;
    $isChoiceSubject = static function (mixed $subject): bool {
        $group = strtolower(trim((string) data_get($subject, 'group_code', '').' '.data_get($subject, 'group_name', '')));

        return str_contains($group, 'pilihan') || preg_match('/(?:^|\s)p(?:\s|$)/', $group) === 1;
    };
    $subjectGroups = $usesChoiceGroups
        ? ['Kelompok Umum' => array_values(array_filter($subjects, fn ($subject) => ! $isChoiceSubject($subject))), 'Kelompok Pilihan' => array_values(array_filter($subjects, $isChoiceSubject))]
        : ['Kelompok Umum' => $subjects];
    $extracurricular = data_get($homeroom, 'extracurricular', data_get($homeroom, 'extracurricular_data', []));
    $extracurricular = is_array($extracurricular)
        ? collect($extracurricular)
            ->filter(fn ($item): bool => filled(trim((string) data_get($item, 'name'))))
            ->take(5)
            ->values()
            ->all()
        : [];
    $homeroomSignature = collect($signatures)->first(fn ($signature) => str_contains(strtolower((string) data_get($signature, 'label')), 'wali kelas'));
    $signatureDate = data_get($homeroomSignature, 'place_date', collect($signatures)->pluck('place_date')->filter()->first());
    $astsPredicate = static function (mixed $score): string {
        if ($score === null || $score === '' || ! is_numeric($score)) {
            return '-';
        }

        $score = (float) $score;

        return $score >= 86 ? 'A' : ($score >= 76 ? 'B' : ($score >= 70 ? 'C' : 'D'));
    };
@endphp

<div class="report-footer">{{ $footerText }}</div>

@php($letterhead = 'assessment.reports._asts-letterhead')
<section class="report-page report-page--asts-scores" style="--kop-title-spacing: {{ $kopTitleSpacing }}pt; --title-identity-spacing: {{ $titleIdentitySpacing }}pt;">
    @include($letterhead)
    <h1 class="report-title">{{ $reportTitle }}</h1>
    <p class="report-subtitle">Tahun Pelajaran {{ $academicYear }}</p>
    @include('assessment.reports._asts-identity')

    @foreach ($subjectGroups as $groupName => $groupSubjects)
        @if ($usesChoiceGroups || $loop->first)<p class="asts-subject-group">{{ $groupName }}</p>@endif
        <table class="scores asts-scores"><thead><tr><th class="scores__number">No.</th><th>Mata Pelajaran</th><th class="scores__score">{{ $scoreLabel }}</th><th class="scores__predicate">{{ $predicateLabel }}</th></tr></thead><tbody>
            @forelse ($groupSubjects as $index => $subject)<tr><td class="scores__number">{{ $index + 1 }}</td><td>{{ data_get($subject, 'name', data_get($subject, 'subject_name', '-')) }}</td><td class="scores__score">{{ \App\Support\Assessment\AssessmentNumberFormatter::score(data_get($subject, 'final_score', data_get($subject, 'score')), 0, '') }}</td><td class="scores__predicate">{{ data_get($subject, 'final_score', data_get($subject, 'score')) !== null && data_get($subject, 'final_score', data_get($subject, 'score')) !== '' ? $astsPredicate(data_get($subject, 'final_score', data_get($subject, 'score'))) : '' }}</td></tr>
            @empty<tr><td class="empty-row" colspan="4">Belum ada mata pelajaran pada kelompok ini.</td></tr>@endforelse
        </tbody></table>
    @endforeach

    <p class="asts-kktp-title">Tabel Interval berdasarkan KKTP</p>
    <table class="asts-kktp"><thead><tr><th>KKTP</th><th colspan="4">Predikat</th></tr><tr><th>81</th><th>D<br><span>Kurang</span></th><th>C<br><span>Cukup</span></th><th>B<br><span>Baik</span></th><th>A<br><span>Sangat Baik</span></th></tr></thead><tbody><tr><td>Interval Nilai</td><td>&lt;70</td><td>70-75</td><td>76-85</td><td>86-100</td></tr></tbody></table>
</section>

<div class="report-page-break"></div>

<section class="report-page report-page--asts-summary" style="--kop-title-spacing: {{ $kopTitleSpacing }}pt; --title-identity-spacing: {{ $titleIdentitySpacing }}pt;">
    @include($letterhead)
    <h1 class="report-title">{{ $reportTitle }}</h1>
    @include('assessment.reports._asts-identity', ['summary' => true])
    <table class="asts-summary-grid{{ empty($extracurricular) ? ' asts-summary-grid--attendance-only' : '' }}"><tr><td>
        <p class="section-title">Ketidakhadiran</p>
        <table class="summary-table summary-table--attendance"><tr><th>Sakit</th><td><span class="attendance-value">{!! ($days = (int) data_get($homeroom, 'sick_days', 0)) === 0 ? '-' : $days.'&nbsp;hari' !!}</span></td></tr><tr><th>Izin</th><td><span class="attendance-value">{!! ($days = (int) data_get($homeroom, 'permission_days', 0)) === 0 ? '-' : $days.'&nbsp;hari' !!}</span></td></tr><tr><th>Tanpa Keterangan</th><td><span class="attendance-value">{!! ($days = (int) data_get($homeroom, 'absent_days', 0)) === 0 ? '-' : $days.'&nbsp;hari' !!}</span></td></tr></table>
    </td>@if (filled($extracurricular))<td>
        <p class="section-title">Ekstrakurikuler</p>
        <table class="summary-table asts-extracurricular"><thead><tr><th>No.</th><th>Nama Ekstrakurikuler</th><th>Predikat</th></tr></thead><tbody>@foreach ($extracurricular as $index => $item)<tr><td>{{ $index + 1 }}</td><td>{{ data_get($item, 'name') }}</td><td>{{ data_get($item, 'grade', data_get($item, 'description', data_get($item, 'level', '-'))) }}</td></tr>@endforeach</tbody></table>
    </td>@endif</tr></table>

    <table class="signatures asts-signatures" style="--table-signature-spacing: {{ $tableSignatureSpacing }}pt; --signature-space-height: {{ $signatureSpacing }}pt;"><tr><td>{{ data_get($signatures, '0.label', 'Orang Tua/Wali') }}</td><td>{{ $signatureDate ?: 'Tangerang, ....................' }}<br>{{ data_get($homeroomSignature, 'label', 'Wali Kelas') }}</td></tr><tr class="signature-spaces"><td><div class="signature-space"></div></td><td><div class="signature-space"></div></td></tr><tr class="signature-names"><td><div class="signature-name signature-name--blank">(................................................)</div></td><td><div class="signature-name">{{ filled(data_get($homeroomSignature, 'name')) && data_get($homeroomSignature, 'name') !== '-' ? data_get($homeroomSignature, 'name') : '................................................' }}</div></td></tr></table>
</section>
