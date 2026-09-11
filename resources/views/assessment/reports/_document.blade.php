@php
    $reportKind = strtoupper($reportKind);
@endphp
@if ($reportKind === 'ASTS')
    @include('assessment.reports._document-asts', ['reportKind' => $reportKind])
@else
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
    $reportTitles = [
        'ASTS' => 'LAPORAN HASIL ASESMEN SUMATIF TENGAH SEMESTER (ASTS)',
        'ASAS' => 'LAPORAN HASIL ASESMEN SUMATIF AKHIR SEMESTER (ASAS)',
        'ASAT' => 'LAPORAN HASIL ASESMEN SUMATIF AKHIR TAHUN (ASAT)',
    ];
    $reportKind = strtoupper($reportKind);
    $title = $reportTitles[$reportKind] ?? $reportTitles['ASTS'];
    $academicYear = data_get($period, 'academic_year', '-');
    $scoreLabel = trim((string) data_get($templateSettings, 'score_label', 'Nilai')) ?: 'Nilai';
    $predicateLabel = trim((string) data_get($templateSettings, 'predicate_label', 'Predikat')) ?: 'Predikat';
    $showPredicate = (bool) data_get($templateSettings, 'show_predicate', true);
    $showDescription = (bool) data_get($templateSettings, 'show_description', true);
    $scoreColumnCount = 3 + (int) $showPredicate + (int) $showDescription;
    $extracurricular = data_get($homeroom, 'extracurricular', data_get($homeroom, 'extracurricular_data', []));
    $achievements = data_get($homeroom, 'achievements', data_get($homeroom, 'achievement_data', []));
    $extracurricular = is_array($extracurricular) ? $extracurricular : [];
    $achievements = is_array($achievements) ? $achievements : [];
    $kokurikuler = trim(strip_tags((string) data_get($homeroom, 'kokurikuler', '-')));
    $kokurikuler = $kokurikuler !== '' && $kokurikuler !== '-' ? \Illuminate\Support\Str::limit($kokurikuler, 420) : 'Diisi manual oleh wali kelas.';
    $note = trim(strip_tags((string) data_get($homeroom, 'note', data_get($homeroom, 'homeroom_note', '-'))));
    $note = $note !== '' ? \Illuminate\Support\Str::limit($note, 650) : '-';
    $showPromotionStatus = $reportKind === 'ASAS' && (bool) data_get($period, 'collect_promotion_status', true) && filled(data_get($homeroom, 'promotion_status'));
    $compactList = fn (array $items): string => \Illuminate\Support\Str::limit(collect($items)->map(fn ($item) => is_array($item) ? data_get($item, 'name', '-').' - '.data_get($item, 'description', data_get($item, 'grade', data_get($item, 'level', '-'))) : (string) $item)->implode('; ') ?: '-', 380);
@endphp

<div class="report-footer">Dengan Teladan Menjadi Mulia &middot; Rapor {{ $reportKind }} {{ data_get($school, 'name', 'SMA Al Furqon Boarding School') }}&middot; Tahun Pelajaran {{ $academicYear }}</div>

<section class="report-page report-page--scores">
    <table class="letterhead"><tr>
        <td class="letterhead__logo">@if ($logoIsSafe)<img src="{{ $logo }}" alt="">@endif</td>
        <td class="letterhead__school"><p class="letterhead__school-name">{{ data_get($school, 'name', 'SMA AFBS') }}</p>
            @if (filled(data_get($school, 'address')))<p class="letterhead__school-info">{{ data_get($school, 'address') }}</p>@endif
            @if (filled(data_get($school, 'contact')))<p class="letterhead__school-info">{{ data_get($school, 'contact') }}</p>@endif
        </td><td class="letterhead__logo"></td>
    </tr></table>
    <hr class="letterhead-rule">
    <h1 class="report-title">{{ $title }}</h1>
    <p class="report-subtitle">Tahun Pelajaran {{ $academicYear }} &middot; Semester {{ data_get($period, 'semester', '-') }}</p>
    <table class="identity"><tr>
        <td class="identity__label">Nama Siswa</td><td class="identity__separator">:</td><td>{{ data_get($student, 'name', '-') }}</td>
        <td class="identity__label">Kelas</td><td class="identity__separator">:</td><td>{{ data_get($student, 'class_name', '-') }}</td>
    </tr><tr>
        <td class="identity__label">NIS / NISN</td><td class="identity__separator">:</td><td>{{ data_get($student, 'nis', '-') }} / {{ data_get($student, 'nisn', '-') }}</td>
        <td class="identity__label">Jenis Laporan</td><td class="identity__separator">:</td><td>{{ $reportKind }}</td>
    </tr></table>
    <table class="scores"><thead><tr>
        <th class="scores__number">No.</th><th>Mata Pelajaran</th><th class="scores__score">{{ $scoreLabel }}</th>
        @if ($showPredicate)<th class="scores__predicate">{{ $predicateLabel }}</th>@endif
        @if ($showDescription)<th>Capaian Kompetensi</th>@endif
    </tr></thead><tbody>
        @forelse ($subjects as $index => $subject)<tr>
            <td class="scores__number">{{ $index + 1 }}</td><td>{{ data_get($subject, 'name', data_get($subject, 'subject_name', '-')) }}</td>
            <td class="scores__score">{{ \App\Support\Assessment\AssessmentNumberFormatter::scoreRapor(data_get($subject, 'final_score', data_get($subject, 'score'))) }}</td>
            @if ($showPredicate)<td class="scores__predicate">{{ data_get($subject, 'predicate', '-') }}</td>@endif
            @if ($showDescription)<td class="scores__description">{{ \Illuminate\Support\Str::limit((string) data_get($subject, 'description', '-'), 145) }}</td>@endif
        </tr>@empty<tr><td class="empty-row" colspan="{{ $scoreColumnCount }}">Belum ada hasil mata pelajaran pada snapshot ini.</td></tr>@endforelse
    </tbody></table>
    <p class="section-title report-page-one-section">B. Kokurikuler</p>
    <table class="summary-table kokurikuler-table"><tr><td class="manual-writing-space">{{ $kokurikuler }}</td></tr></table>
</section>

<div class="report-page-break"></div>

<section class="report-page report-page--summary">
    <p class="section-title">C. Ekstrakurikuler</p>
    <table class="summary-table"><tr><th>Ekstrakurikuler</th><td>{{ $compactList($extracurricular) }}</td></tr></table>
    <p class="section-title">D. Sikap</p>
    <table class="summary-table"><tr><th>Spiritual</th><td>{{ \Illuminate\Support\Str::limit((string) data_get($homeroom, 'spiritual_description', data_get($homeroom, 'spiritual_predicate', '-')), 180) ?: '-' }}</td></tr><tr><th>Sosial</th><td>{{ \Illuminate\Support\Str::limit((string) data_get($homeroom, 'social_description', data_get($homeroom, 'social_predicate', '-')), 180) ?: '-' }}</td></tr></table>
    <p class="section-title">E. Prestasi</p>
    <table class="summary-table"><tr><th>Prestasi</th><td>{{ $compactList($achievements) }}</td></tr></table>
    <p class="section-title">F. Ketidakhadiran</p>
    <table class="summary-table summary-table--attendance"><tr><th>Sakit</th><td><span class="attendance-value">{{ (int) data_get($homeroom, 'sick_days', 0) }}&nbsp;hari</span></td></tr><tr><th>Izin</th><td><span class="attendance-value">{{ (int) data_get($homeroom, 'permission_days', 0) }}&nbsp;hari</span></td></tr><tr><th>Tanpa Keterangan</th><td><span class="attendance-value">{{ (int) data_get($homeroom, 'absent_days', 0) }}&nbsp;hari</span></td></tr></table>
    <p class="section-title">G. Catatan Wali Kelas</p>
    <table class="summary-table"><tr><td class="report-writing-space">{{ $note }}</td></tr></table>
    @if ($showPromotionStatus)<p class="section-title">Keterangan Naik Kelas</p><table class="summary-table"><tr><td>{{ data_get($homeroom, 'promotion_status') }}</td></tr></table>@endif
    @php
        $signatureColumns = count($signatures) > 0 ? array_values($signatures) : [
            ['label' => 'Orang Tua/Wali', 'name' => '-'],
            ['label' => 'Wali Kelas', 'name' => '-'],
            ['label' => 'Kepala Sekolah', 'name' => '-'],
        ];
        // Normalize by label so the approved layout is stable even when snapshot order changes.
        $signatureFor = function (string $type) use ($signatureColumns): array {
            foreach ($signatureColumns as $signature) {
                $label = strtolower(trim((string) data_get($signature, 'label', '')));
                $matches = match ($type) {
                    'parent' => str_contains($label, 'orang tua') || str_contains($label, 'ortu') || str_contains($label, 'wali murid'),
                    'homeroom' => str_contains($label, 'wali kelas') || str_contains($label, 'homeroom'),
                    'principal' => str_contains($label, 'kepala sekolah') || str_contains($label, 'principal'),
                    default => false,
                };
                if ($matches) {
                    return $signature;
                }
            }
            return ['label' => $type === 'parent' ? 'Orang Tua/Wali' : ($type === 'homeroom' ? 'Wali Kelas' : 'Kepala Sekolah'), 'name' => '-'];
        };
        $signatureParent = $signatureFor('parent');
        $signatureHomeroom = $signatureFor('homeroom');
        $signaturePrincipal = $signatureFor('principal');
        $signatureDate = collect($signatureColumns)->pluck('place_date')->filter()->first();
        $renderSignature = function (array $signature) {
            $name = data_get($signature, 'name');
            return ['name' => filled($name) && $name !== '-' ? $name : '................................................', 'blank' => ! filled($name) || $name === '-'];
        };
    @endphp
    <table class="signatures signatures--stacked">
        @if ($signatureDate)<tr><td class="signature-date" colspan="2">{{ $signatureDate }}</td></tr>@endif
        <tr class="signature-labels"><td>{{ data_get($signatureParent, 'label', 'Orang Tua/Wali') }}</td><td>{{ data_get($signatureHomeroom, 'label', 'Wali Kelas') }}</td></tr>
        <tr class="signature-spaces"><td><div class="signature-space signature-space--manual"></div></td><td><div class="signature-space signature-space--manual"></div></td></tr>
        <tr class="signature-names"><td>@php($rendered = $renderSignature($signatureParent))<div class="signature-name{{ $rendered['blank'] ? ' signature-name--blank' : '' }}">{{ $rendered['name'] }}</div>@if (filled(data_get($signatureParent, 'identifier')))<div class="signature-identifier">{{ data_get($signatureParent, 'identifier') }}</div>@endif</td><td>@php($rendered = $renderSignature($signatureHomeroom))<div class="signature-name{{ $rendered['blank'] ? ' signature-name--blank' : '' }}">{{ $rendered['name'] }}</div>@if (filled(data_get($signatureHomeroom, 'identifier')))<div class="signature-identifier">{{ data_get($signatureHomeroom, 'identifier') }}</div>@endif</td></tr>
        <tr class="signature-labels signature-labels--principal"><td colspan="2">{{ data_get($signaturePrincipal, 'label', 'Kepala Sekolah') }}</td></tr>
        <tr class="signature-spaces"><td colspan="2"><div class="signature-space signature-space--manual signature-space--principal"></div></td></tr>
        <tr class="signature-names"><td colspan="2">@php($rendered = $renderSignature($signaturePrincipal))<div class="signature-name{{ $rendered['blank'] ? ' signature-name--blank' : '' }}">{{ $rendered['name'] }}</div>@if (filled(data_get($signaturePrincipal, 'identifier')))<div class="signature-identifier">{{ data_get($signaturePrincipal, 'identifier') }}</div>@endif</td></tr>
    </table>
</section>
@endif
