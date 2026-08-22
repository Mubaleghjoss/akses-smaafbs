@php
    $school = is_array(data_get($snapshot, 'school')) ? data_get($snapshot, 'school') : [];
    $period = is_array(data_get($snapshot, 'period')) ? data_get($snapshot, 'period') : [];
    $student = is_array(data_get($snapshot, 'student')) ? data_get($snapshot, 'student') : [];
    $homeroom = is_array(data_get($snapshot, 'homeroom')) ? data_get($snapshot, 'homeroom') : [];
    $subjects = is_array(data_get($snapshot, 'subjects')) ? data_get($snapshot, 'subjects') : [];
    $signatures = is_array(data_get($snapshot, 'signatures')) ? data_get($snapshot, 'signatures') : [];
    $layoutPages = app(\App\Support\Assessment\Reporting\AssessmentReportLayout::class)->pages($templateSettings);
    $subjectGroups = collect($subjects)->groupBy(fn (array $subject) => data_get($subject, 'group_name', 'Belum Dikelompokkan'));
    $extracurricular = data_get($homeroom, 'extracurricular_data', []);
    $achievements = data_get($homeroom, 'achievement_data', []);
    $extracurricular = is_array($extracurricular) ? $extracurricular : [];
    $achievements = is_array($achievements) ? $achievements : [];
    $logo = trim((string) data_get($school, 'logo_data_uri'));
    $logoIsSafe = preg_match('#^data:image/(?:png|jpeg|webp);base64,#i', $logo) === 1;
    $watermark = trim((string) data_get($templateSettings, 'watermark_data_uri'));
    $watermarkSafe = preg_match('#^data:image/png;base64,#i', $watermark) === 1;
    $watermarkOpacity = min(25, max(5, (int) data_get($templateSettings, 'watermark_opacity', 10))) / 100;
    $watermarkPosition = in_array(data_get($templateSettings, 'watermark_position'), ['top', 'center', 'bottom'], true)
        ? data_get($templateSettings, 'watermark_position')
        : 'center';
    $watermarkWidth = min(90, max(20, (int) data_get($templateSettings, 'watermark_width', 60)));
    $title = trim((string) data_get($templateSettings, 'report_title'));
    $title = $title !== '' ? $title : ($reportKind === 'ASAS' ? 'LAPORAN HASIL ASESMEN AKHIR SEMESTER' : 'LAPORAN HASIL ASESMEN TENGAH SEMESTER');
    $showPredicate = (bool) data_get($templateSettings, 'show_predicate', true);
    $scoreLabel = trim((string) data_get($templateSettings, 'score_label', 'Nilai Akhir')) ?: 'Nilai Akhir';
    $predicateLabel = trim((string) data_get($templateSettings, 'predicate_label', 'Predikat')) ?: 'Predikat';
@endphp

@foreach($layoutPages as $pageNumber => $sections)
    <section class="report-page report-page--structured">
        @if ((bool) data_get($snapshot, 'meta.preview', false))
            <div class="report-preview-label">
                {{ (bool) data_get($snapshot, 'meta.preview_data_incomplete', false) ? 'PRATINJAU — DATA BELUM LENGKAP' : 'PRATINJAU — BUKAN RAPOR RESMI' }}
            </div>
        @endif
        @if ($watermarkSafe && (bool) data_get($templateSettings, 'watermark_enabled', false))
            <div
                class="report-watermark report-watermark--{{ $watermarkPosition }}"
                style="opacity: {{ $watermarkOpacity }}; width: {{ $watermarkWidth }}%;"
            >
                <img src="{{ $watermark }}" alt="">
            </div>
        @endif

        @foreach($sections as $section)
            @php
                $sectionType = (string) data_get($section, 'type');
                $sectionTitle = (string) data_get($section, 'title', \App\Support\Assessment\Reporting\AssessmentReportLayout::sectionOptions()[$sectionType] ?? '');
            @endphp

            @switch($sectionType)
                @case('identity')
                    <table class="letterhead">
                        <tr>
                            <td class="letterhead__logo">
                                @if ($logoIsSafe)<img src="{{ $logo }}" alt="">@endif
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
                    <p class="report-subtitle">Tahun Pelajaran {{ data_get($period, 'academic_year', '-') }} · Semester {{ data_get($period, 'semester', '-') }}</p>
                    <table class="identity">
                        <tr>
                            <td class="identity__label">Nama Peserta Didik</td><td class="identity__separator">:</td><td>{{ data_get($student, 'name', '-') }}</td>
                            <td class="identity__label">Kelas</td><td class="identity__separator">:</td><td>{{ data_get($student, 'class_name', '-') }}</td>
                        </tr>
                        <tr>
                            <td class="identity__label">NIS / NISN</td><td class="identity__separator">:</td>
                            <td>{{ data_get($student, 'nis', '-') }} / {{ data_get($student, 'nisn', '-') }}</td>
                            <td class="identity__label">Jenis Laporan</td><td class="identity__separator">:</td><td>{{ $reportKind }}</td>
                        </tr>
                    </table>
                    @break

                @case('attitudes')
                    <p class="section-title">{{ $sectionTitle }}</p>
                    <table class="scores report-attitudes">
                        <thead><tr><th>Aspek Sikap</th><th>Predikat</th><th>Deskripsi</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Sikap Spiritual</td>
                                <td class="scores__predicate">{{ data_get($homeroom, 'spiritual_predicate', '-') ?: '-' }}</td>
                                <td>{{ data_get($homeroom, 'spiritual_description', '-') ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td>Sikap Sosial</td>
                                <td class="scores__predicate">{{ data_get($homeroom, 'social_predicate', '-') ?: '-' }}</td>
                                <td>{{ data_get($homeroom, 'social_description', '-') ?: '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    @break

                @case('subject_summary')
                    <p class="section-title">{{ $sectionTitle }}</p>
                    <table class="scores">
                        <thead>
                            <tr><th class="scores__number">No.</th><th>Mata Pelajaran</th><th class="scores__score">{{ $scoreLabel }}</th>@if($showPredicate)<th class="scores__predicate">{{ $predicateLabel }}</th>@endif</tr>
                        </thead>
                        <tbody>
                            @forelse($subjectGroups as $groupName => $groupSubjects)
                                <tr class="scores__group"><td colspan="{{ $showPredicate ? 4 : 3 }}">{{ $groupName }}</td></tr>
                                @foreach($groupSubjects as $subject)
                                    <tr>
                                        <td class="scores__number">{{ $loop->iteration }}</td>
                                        <td>{{ data_get($subject, 'name', '-') }}</td>
                                        <td class="scores__score">{{ \App\Support\Assessment\AssessmentNumberFormatter::score(data_get($subject, 'final_score')) }}</td>
                                        @if($showPredicate)<td class="scores__predicate">{{ data_get($subject, 'predicate', '-') ?: '-' }}</td>@endif
                                    </tr>
                                @endforeach
                            @empty
                                <tr><td class="empty-row" colspan="{{ $showPredicate ? 4 : 3 }}">Belum ada hasil mata pelajaran.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @break

                @case('subject_competencies')
                    <p class="section-title">{{ $sectionTitle }}</p>
                    <table class="scores report-competencies">
                        <thead><tr><th class="scores__number">No.</th><th>Mata Pelajaran</th><th class="scores__score">{{ $scoreLabel }}</th><th>Capaian Kompetensi</th></tr></thead>
                        <tbody>
                            @forelse($subjectGroups as $groupName => $groupSubjects)
                                <tr class="scores__group"><td colspan="4">{{ $groupName }}</td></tr>
                                @foreach($groupSubjects as $subject)
                                    <tr>
                                        <td class="scores__number">{{ $loop->iteration }}</td>
                                        <td>{{ data_get($subject, 'name', '-') }}</td>
                                        <td class="scores__score">{{ \App\Support\Assessment\AssessmentNumberFormatter::score(data_get($subject, 'final_score')) }}</td>
                                        <td class="scores__description">{{ data_get($subject, 'description', '-') ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr><td class="empty-row" colspan="4">Belum ada capaian kompetensi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @break

                @case('extracurricular')
                    <p class="section-title">{{ $sectionTitle }}</p>
                    <table class="summary-table">
                        <thead><tr><th>Ekstrakurikuler</th><th>Keterangan</th></tr></thead>
                        <tbody>
                            @forelse($extracurricular as $item)
                                <tr><td>{{ data_get($item, 'name', '-') }}</td><td>{{ data_get($item, 'description', data_get($item, 'grade', '-')) }}</td></tr>
                            @empty
                                <tr><td colspan="2">-</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @break

                @case('achievements')
                    <p class="section-title">{{ $sectionTitle }}</p>
                    <table class="summary-table">
                        <thead><tr><th>Jenis Prestasi</th><th>Keterangan</th></tr></thead>
                        <tbody>
                            @forelse($achievements as $item)
                                <tr><td>{{ data_get($item, 'name', '-') }}</td><td>{{ data_get($item, 'description', data_get($item, 'level', '-')) }}</td></tr>
                            @empty
                                <tr><td colspan="2">-</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @break

                @case('attendance')
                    <p class="section-title">{{ $sectionTitle }}</p>
                    <table class="summary-table summary-table--attendance">
                        <tr><th>Sakit</th><td><span class="attendance-value">{{ (int) data_get($homeroom, 'sick_days', 0) }}&nbsp;hari</span></td><th>Izin</th><td><span class="attendance-value">{{ (int) data_get($homeroom, 'permission_days', 0) }}&nbsp;hari</span></td><th>Tanpa Keterangan</th><td><span class="attendance-value">{{ (int) data_get($homeroom, 'absent_days', 0) }}&nbsp;hari</span></td></tr>
                    </table>
                    @break

                @case('homeroom_note')
                    <p class="section-title">{{ $sectionTitle }}</p>
                    <table class="summary-table"><tr><td class="report-writing-space">{{ data_get($homeroom, 'homeroom_note', '-') ?: '-' }}</td></tr></table>
                    @break

                @case('semester_status')
                    @if((bool) data_get($period, 'collect_promotion_status', false))
                        <p class="section-title">{{ $sectionTitle }}</p>
                        <table class="summary-table">
                            <tr>
                                <th>{{ data_get($templateSettings, 'semester_status_label', 'Status Semester/Kenaikan Kelas') }}</th>
                                <td>{{ data_get($homeroom, 'promotion_status', '-') ?: '-' }}</td>
                            </tr>
                        </table>
                    @endif
                    @break

                @case('parent_response')
                    <p class="section-title">{{ $sectionTitle }}</p>
                    <table class="summary-table"><tr><td class="report-writing-space report-writing-space--parent">&nbsp;</td></tr></table>
                    @break

                @case('signatures')
                    <p class="section-title">{{ $sectionTitle }}</p>
                    <table class="signatures">
                        <tr>
                            @foreach($signatures as $signature)
                                <td>
                                    <div>{{ data_get($signature, 'place_date', data_get($period, 'report_date', '')) }}</div>
                                    <div>{{ data_get($signature, 'label', 'Mengetahui') }}</div>
                                    <div class="signature-space"></div>
                                    <div class="signature-name">{{ data_get($signature, 'name', '-') }}</div>
                                    @if(filled(data_get($signature, 'identifier')))<div>{{ data_get($signature, 'identifier') }}</div>@endif
                                </td>
                            @endforeach
                        </tr>
                    </table>
                    @break
            @endswitch
        @endforeach

        <div class="footer-note">
            Halaman {{ $loop->iteration }}/{{ count($layoutPages) }}
            · Snapshot revisi {{ data_get($snapshot, 'meta.revision', '-') }}
            · Template v{{ data_get($snapshot, 'template.version', '-') }}
        </div>
    </section>
@endforeach
