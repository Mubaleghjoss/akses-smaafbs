<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Filament\Resources\AssessmentAuditLogResource;
use App\Filament\Resources\AssessmentPeriodResource;
use App\Filament\Resources\AssessmentReportTemplateResource;
use App\Filament\Resources\AssessmentSchemeResource;
use App\Filament\Resources\AssessmentSubjectCategoryResource;
use App\Filament\Resources\AssessmentSubjectResource;
use App\Filament\Resources\DataSiswaResource;
use App\Filament\Resources\GuruTendikResource;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\AuditLog;
use App\Models\Assessment\HomeroomAssignment;
use App\Models\Assessment\ReportTemplate;
use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\SubjectCategory;
use App\Models\Assessment\TeachingAssignment;
use App\Models\DataSiswa;
use App\Models\GuruTendik;
use App\Models\Rombel;
use App\Models\User;
use App\Support\Assessment\AssessmentPageMap;
use App\Support\Assessment\AssessmentStatusScope;
use App\Support\Storage\HostingStorageAudit;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;

class AssessmentDashboard extends AssessmentPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Pengaturan Penilaian';

    protected static ?string $slug = 'penilaian';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.assessment.dashboard';

    #[Url(as: 'period')]
    public ?int $periodId = null;

    public function mount(): void
    {
        $ids = array_map('intval', array_keys($this->getPeriodOptions()));
        if (! $this->periodId || ! in_array($this->periodId, $ids, true)) {
            $this->periodId = $ids[0] ?? null;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Pengaturan Penilaian';
    }

    /** @return array<string, mixed>|null */
    public function getHostingStorageStatus(): ?array
    {
        return app(HostingStorageAudit::class)->latest();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pantau kesiapan master, input guru, verifikasi, dan pembuatan rapor untuk seluruh jenis penilaian.';
    }

    public function getPeriodOptions(): array
    {
        return $this->scopePeriods(AssessmentPeriod::query())
            ->latest('id')
            ->get()
            ->mapWithKeys(function (AssessmentPeriod $period): array {
                $type = $period->type instanceof AssessmentType ? $period->type->label() : strtoupper((string) $period->type);

                return [$period->getKey() => "{$type} · {$period->name}"];
            })
            ->all();
    }

    /**
     * Pusat kerja per JENIS penilaian (ASTS · ASAS · ASAT).
     *
     * Dibangun dari enum + peta halaman, sehingga jenis penilaian baru langsung
     * muncul di sini tanpa menyunting blade atau menambah kartu manual.
     *
     * @return array<int, array{label:string,long_label:string,url:?string,period_count:int,semesters:string}>
     */
    public function getTypeCenters(): array
    {
        $periodCounts = AssessmentPeriod::query()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        return collect(AssessmentType::cases())
            ->map(function (AssessmentType $type) use ($periodCounts): array {
                $page = AssessmentPageMap::page($type, 'hub');

                return [
                    'label' => $type->label(),
                    'long_label' => $type->namaPanjang(),
                    'url' => $page::canAccess() ? $page::getUrl() : null,
                    'period_count' => (int) ($periodCounts[$type->value] ?? 0),
                    'semesters' => collect($type->semesterYangDiizinkan())
                        ->map(fn (string $semester): string => ucfirst($semester))
                        ->implode(' & '),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSettingCards(): array
    {
        return [
            [
                'title' => 'Kategori Mapel',
                'points' => [
                    'Atur Mapel Wajib dan Mapel Pilihan yang tampil sebagai kelompok rapor.',
                    'Kategori dapat berbeda pada setiap kelas.',
                ],
                'action' => 'Kelola Kategori',
                'icon' => 'heroicon-o-tag',
                'tone' => 'warning',
                'value' => AssessmentSubjectCategoryResource::canViewAny()
                    ? SubjectCategory::query()->where('is_active', true)->count()
                    : '—',
                'caption' => 'kategori aktif',
                'url' => AssessmentSubjectCategoryResource::canViewAny() ? AssessmentSubjectCategoryResource::getUrl() : null,
            ],
            [
                'title' => 'Guru Mapel & Kelas',
                'points' => [
                    'Hubungkan guru dengan mapel dan kelas per semester.',
                    'Menentukan penugasan nilai yang tampil pada akun guru.',
                ],
                'action' => 'Buka Mapel Penilaian',
                'icon' => 'heroicon-o-book-open',
                'tone' => 'success',
                'value' => GuruTendikResource::canViewAny()
                    ? TeachingAssignment::query()->where('is_active', true)->count()
                    : '—',
                'caption' => 'penugasan mapel aktif',
                'url' => AssessmentSubjectResource::canViewAny() ? AssessmentSubjectResource::getUrl() : null,
            ],
            [
                'title' => 'Wali Kelas',
                'points' => [
                    'Tetapkan satu wali kelas untuk setiap rombel dan semester.',
                    'Menentukan akses rekap dan identitas wali pada rapor.',
                ],
                'action' => 'Atur Wali Kelas',
                'icon' => 'heroicon-o-user-group',
                'tone' => 'warning',
                'value' => GuruTendikResource::canViewAny()
                    ? HomeroomAssignment::query()->where('is_active', true)->count()
                    : '—',
                'caption' => 'penugasan walas aktif',
                'url' => GuruTendikResource::canViewAny() ? GuruTendikResource::getUrl() : null,
            ],
            [
                'title' => 'Periode Penilaian',
                'points' => [
                    'Buat periode ASTS atau ASAS dan pilih kelas peserta.',
                    'Buka, verifikasi, kunci, dan terbitkan periode.',
                ],
                'action' => 'Kelola Periode',
                'icon' => 'heroicon-o-calendar-days',
                'tone' => 'primary',
                'value' => AssessmentPeriodResource::canViewAny() ? AssessmentPeriod::query()->count() : '—',
                'caption' => 'periode tersedia',
                'url' => AssessmentPeriodResource::canViewAny() ? AssessmentPeriodResource::getUrl() : null,
            ],
            [
                'title' => 'Komponen dan Bobot',
                'points' => [
                    'Atur komponen, KKM, predikat, dan sumber nilai.',
                    'Total bobot komponen aktif wajib tepat 100%.',
                ],
                'action' => 'Atur Komponen',
                'icon' => 'heroicon-o-adjustments-horizontal',
                'tone' => 'success',
                'value' => AssessmentSchemeResource::canViewAny() ? AssessmentScheme::query()->count() : '—',
                'caption' => 'skema penilaian',
                'url' => AssessmentSchemeResource::canViewAny() ? AssessmentSchemeResource::getUrl() : null,
            ],
            [
                'title' => 'Template Rapor',
                'points' => [
                    'Atur identitas sekolah, tanda tangan, layout, dan watermark.',
                    'Template yang sudah dipakai tetap menjadi versi historis.',
                ],
                'action' => 'Kelola Template',
                'icon' => 'heroicon-o-document-text',
                'tone' => 'info',
                'value' => AssessmentReportTemplateResource::canViewAny() ? ReportTemplate::query()->count() : '—',
                'caption' => 'template rapor',
                'url' => AssessmentReportTemplateResource::canViewAny() ? AssessmentReportTemplateResource::getUrl() : null,
            ],
            [
                'title' => 'Impor Master Resmi',
                'points' => [
                    'Unduh workbook resmi dan lengkapi master secara massal.',
                    'Selalu tinjau preview sebelum data diterapkan.',
                ],
                'action' => 'Buka Impor Master',
                'icon' => 'heroicon-o-arrow-up-tray',
                'tone' => 'warning',
                'value' => 'Excel',
                'caption' => 'preview sebelum apply',
                'url' => AssessmentMasterImport::canAccess() ? AssessmentMasterImport::getUrl() : null,
            ],
            [
                'title' => 'Log Perubahan',
                'points' => [
                    'Telusuri perubahan periode, nilai, dan penerbitan.',
                    'Lihat pelaku, waktu, serta alasan setiap koreksi.',
                ],
                'action' => 'Buka Log Perubahan',
                'icon' => 'heroicon-o-clipboard-document-check',
                'tone' => 'gray',
                'value' => AssessmentAuditLogResource::canViewAny() ? AuditLog::query()->count() : '—',
                'caption' => 'aktivitas tercatat',
                'url' => AssessmentAuditLogResource::canViewAny() ? AssessmentAuditLogResource::getUrl() : null,
            ],
        ];
    }

    /**
     * @return array{
     *     steps: array<int, array<string, mixed>>,
     *     notes: array<int, string>,
     *     type_links: array<int, array{label:string,long_label:string,url:?string}>
     * }
     */
    public function getSetupWorkflow(): array
    {
        $activeTeacherCount = GuruTendik::query()
            ->whereRaw("LOWER(COALESCE(status, '')) = 'aktif'")
            ->count();
        $linkedTeacherCount = User::query()
            ->whereNotNull('guru_tendik_id')
            ->distinct()
            ->count('guru_tendik_id');

        $activeRombels = Rombel::query()
            ->where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'nama']);
        $activeStudentCount = 0;
        $studentsWithoutActiveRombel = 0;

        if (Schema::hasTable('data_siswa')) {
            $activeStudents = DataSiswa::query()->where('status', 'aktif');
            $activeStudentCount = (clone $activeStudents)->count();
            $studentsWithoutActiveRombel = (clone $activeStudents)
                ->where(function (Builder $students) use ($activeRombels): void {
                    $students
                        ->whereNull('rombel_saat_ini')
                        ->orWhere('rombel_saat_ini', '')
                        ->orWhereNotIn('rombel_saat_ini', $activeRombels->pluck('nama'));
                })
                ->count();
        }

        $activeSemesterCount = Semester::query()->where('is_active', true)->count();
        $activeSubjectCount = Subject::query()->where('is_active', true)->count();
        $activeTeachingCount = TeachingAssignment::query()->where('is_active', true)->count();
        $activeHomeroomCount = HomeroomAssignment::query()->where('is_active', true)->count();
        $assignedTeacherIds = TeachingAssignment::query()
            ->where('is_active', true)
            ->pluck('teacher_id')
            ->merge(
                HomeroomAssignment::query()
                    ->where('is_active', true)
                    ->pluck('teacher_id'),
            )
            ->map(fn (mixed $teacherId): int => (int) $teacherId)
            ->filter()
            ->unique()
            ->values();
        $linkedAssignedTeacherIds = User::query()
            ->whereIn('guru_tendik_id', $assignedTeacherIds)
            ->pluck('guru_tendik_id')
            ->map(fn (mixed $teacherId): int => (int) $teacherId)
            ->unique();
        $unlinkedAssignedTeacherCount = $assignedTeacherIds
            ->diff($linkedAssignedTeacherIds)
            ->count();

        // Dihitung per JENIS dari enum, bukan dua variabel tetap: begitu jenis
        // baru ditambahkan (ASAT dan seterusnya) angkanya ikut terhitung.
        $periodCountPerType = AssessmentPeriod::query()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');
        $periodCountSummary = collect(AssessmentType::cases())
            ->map(fn (AssessmentType $type): string => (int) ($periodCountPerType[$type->value] ?? 0).' '.$type->label())
            ->implode(' · ');
        $totalPeriodCount = (int) $periodCountPerType->sum();
        $openPeriodCount = AssessmentPeriod::query()
            ->whereIn('status', [
                AssessmentPeriodStatus::OPEN->value,
                AssessmentPeriodStatus::ENTRY_CLOSED->value,
                AssessmentPeriodStatus::VERIFICATION->value,
                AssessmentPeriodStatus::LOCKED->value,
                AssessmentPeriodStatus::PUBLISHED->value,
            ])
            ->count();

        $activeSchemes = AssessmentScheme::query()
            ->where('is_active', true)
            ->with('components')
            ->get();
        $invalidSchemeCount = $activeSchemes
            ->filter(function (AssessmentScheme $scheme): bool {
                $weight = $scheme->components
                    ->where('is_active', true)
                    ->sum(fn ($component): float => (float) $component->weight);

                return abs($weight - 100.0) > 0.0001;
            })
            ->count();

        $teacherReady = $activeTeacherCount > 0 && $linkedTeacherCount > 0;
        $studentReady = $activeRombels->isNotEmpty()
            && $activeStudentCount > 0
            && $studentsWithoutActiveRombel === 0;
        $assignmentReady = $activeSemesterCount > 0
            && $activeSubjectCount > 0
            && $activeTeachingCount > 0
            && $activeHomeroomCount > 0
            && $unlinkedAssignedTeacherCount === 0;
        $periodReady = $totalPeriodCount > 0;
        $schemeReady = $activeSchemes->isNotEmpty() && $invalidSchemeCount === 0;
        $inputReady = $openPeriodCount > 0;

        $periodUrl = AssessmentPeriodResource::canViewAny()
            ? AssessmentPeriodResource::getUrl()
            : null;

        return [
            'steps' => [
                [
                    'number' => '1',
                    'title' => 'Guru dan Akun Login',
                    'subtitle' => "{$linkedTeacherCount} akun tertaut dari {$activeTeacherCount} guru/tendik aktif.",
                    'detail' => $teacherReady
                        ? 'Identitas guru sudah tersedia. Periksa kembali nama, status, dan akun login.'
                        : 'Lengkapi guru aktif dan tautkan akun pengguna sebelum membuat penugasan.',
                    'ready' => $teacherReady,
                    'url' => GuruTendikResource::canViewAny() ? GuruTendikResource::getUrl() : null,
                    'action' => 'Buka Guru & Tendik',
                ],
                [
                    'number' => '2',
                    'title' => 'Rombel dan Siswa Aktif',
                    'subtitle' => "{$activeStudentCount} siswa dalam {$activeRombels->count()} rombel aktif.",
                    'detail' => $studentsWithoutActiveRombel > 0
                        ? "{$studentsWithoutActiveRombel} siswa aktif belum cocok dengan rombel aktif."
                        : 'Siswa akan diambil otomatis sesuai rombel yang dipilih pada periode.',
                    'ready' => $studentReady,
                    'url' => DataSiswaResource::canViewAny() ? DataSiswaResource::getUrl() : null,
                    'action' => 'Periksa Siswa per Kelas',
                ],
                [
                    'number' => '3',
                    'title' => 'Mapel dan Penugasan Resmi',
                    'subtitle' => "{$activeSubjectCount} mapel · {$activeTeachingCount} penugasan · {$activeHomeroomCount} wali kelas.",
                    'detail' => $unlinkedAssignedTeacherCount > 0
                        ? "{$unlinkedAssignedTeacherCount} guru pada penugasan belum memiliki akun tertaut."
                        : 'Atur per guru untuk perubahan harian; gunakan Impor Master untuk pembaruan massal.',
                    'ready' => $assignmentReady,
                    'url' => GuruTendikResource::canViewAny() ? GuruTendikResource::getUrl() : null,
                    'action' => 'Atur di Guru & Tendik',
                ],
                [
                    'number' => '4',
                    'title' => 'Buat Periode Penilaian',
                    'subtitle' => 'Periode tersedia: '.$periodCountSummary.'.',
                    'detail' => 'Pilih tahun, semester, jenis penilaian, jadwal input, dan rombel peserta.',
                    'ready' => $periodReady,
                    'url' => $periodUrl,
                    'action' => 'Kelola Periode',
                ],
                [
                    'number' => '5',
                    'title' => 'Komponen dan Bobot',
                    'subtitle' => $activeSchemes->count().' skema aktif · '.$invalidSchemeCount.' perlu diperbaiki.',
                    'detail' => 'Pastikan komponen wajib tersedia dan total bobot aktif tepat 100%.',
                    'ready' => $schemeReady,
                    'url' => AssessmentSchemeResource::canViewAny() ? AssessmentSchemeResource::getUrl() : null,
                    'action' => 'Atur Komponen',
                ],
                [
                    'number' => '6',
                    'title' => 'Preflight dan Buka Periode',
                    'subtitle' => "{$openPeriodCount} periode sudah dibuka atau diproses.",
                    'detail' => 'Klik Buka Periode. Sistem menyalin siswa, kelas, guru, mapel, dan wali kelas menjadi snapshot.',
                    'ready' => $inputReady,
                    'url' => $periodUrl,
                    'action' => 'Buka dan Periksa Periode',
                ],
            ],
            'notes' => [
                'Pada Guru & Tendik, buka data guru lalu gunakan tab Penilaian ASTS–ASAS untuk mengatur mapel, kelas mengajar, dan wali kelas.',
                'Impor Master tetap tersedia untuk perubahan massal. Perubahan satu guru dapat dilakukan langsung tanpa mengunggah ulang workbook.',
                'Kolom label mapel lama pada akun hanya keterangan. Penilaian memakai pasangan terstruktur guru–mapel–kelas–semester.',
                'Saat periode dibuka, hanya siswa berstatus aktif yang nama rombelnya cocok dengan rombel aktif pilihan periode yang dimasukkan.',
            ],
            // Tautan pusat per jenis dibangun dari peta halaman agar jenis baru
            // otomatis muncul tanpa menambah kunci 'xxx_url' satu per satu.
            'type_links' => collect(AssessmentType::cases())
                ->map(function (AssessmentType $type): array {
                    $page = AssessmentPageMap::page($type, 'hub');

                    return [
                        'label' => $type->label(),
                        'long_label' => $type->namaPanjang(),
                        'url' => $page::canAccess() ? $page::getUrl() : null,
                    ];
                })
                ->all(),
        ];
    }

    public function getReadiness(): array
    {
        return [
            ['label' => 'Tahun Pelajaran', 'count' => AcademicYear::query()->count(), 'ready' => AcademicYear::query()->exists()],
            ['label' => 'Mata Pelajaran', 'count' => Subject::query()->where('is_active', true)->count(), 'ready' => Subject::query()->where('is_active', true)->exists()],
            ['label' => 'Penugasan Guru', 'count' => TeachingAssignment::query()->where('is_active', true)->count(), 'ready' => TeachingAssignment::query()->where('is_active', true)->exists()],
            ['label' => 'Wali Kelas', 'count' => HomeroomAssignment::query()->where('is_active', true)->count(), 'ready' => HomeroomAssignment::query()->where('is_active', true)->exists()],
            ['label' => 'Periode', 'count' => AssessmentPeriod::query()->count(), 'ready' => AssessmentPeriod::query()->exists()],
        ];
    }

    /**
     * @return array{steps:array<int, array<string,mixed>>,notes:array<int,string>}
     */
    public function getReportSetupWorkflow(): array
    {
        $activeTemplates = ReportTemplate::query()->where('is_active', true)->get();
        $templateIdentityReady = $activeTemplates->contains(function (ReportTemplate $template): bool {
            $settings = is_array($template->settings) ? $template->settings : [];

            return filled(data_get($settings, 'school_name'))
                && filled(data_get($settings, 'principal_name'))
                && filled(data_get($settings, 'place'));
        });
        $subjectCount = Subject::query()->where('is_active', true)->count();
        $ungroupedSubjectCount = Subject::query()
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('report_group_code')
                    ->orWhere('report_group_code', '')
                    ->orWhere('report_group_code', 'BELUM');
            })
            ->count();
        $teachingCount = TeachingAssignment::query()->where('is_active', true)->count();
        $homeroomCount = HomeroomAssignment::query()->where('is_active', true)->count();
        $periodCount = AssessmentPeriod::query()->count();
        $studentCount = Schema::hasTable('data_siswa')
            ? DataSiswa::query()->where('status', 'aktif')->count()
            : 0;

        return [
            'steps' => [
                [
                    'number' => '1',
                    'title' => 'Identitas Sekolah dan Penanda Tangan',
                    'subtitle' => $templateIdentityReady ? 'Template aktif sudah memiliki identitas wajib.' : 'Nama sekolah, kepala sekolah, atau tempat terbit belum lengkap.',
                    'detail' => 'Lengkapi data yang akan dibekukan ke setiap snapshot rapor.',
                    'ready' => $templateIdentityReady,
                    'url' => AssessmentReportTemplateResource::canViewAny() ? AssessmentReportTemplateResource::getUrl() : null,
                    'action' => 'Buka Template Rapor',
                ],
                [
                    'number' => '2',
                    'title' => 'Tahun Pelajaran dan Semester',
                    'subtitle' => AcademicYear::query()->count().' tahun pelajaran · '.Semester::query()->count().' semester.',
                    'detail' => 'Gunakan wizard impor untuk perubahan massal dan preview sebelum data diterapkan.',
                    'ready' => AcademicYear::query()->exists() && Semester::query()->exists(),
                    'url' => AssessmentMasterImport::canAccess() ? AssessmentMasterImport::getUrl() : null,
                    'action' => 'Buka Impor Master',
                ],
                [
                    'number' => '3',
                    'title' => 'Mapel, Kelompok, dan Urutan Rapor',
                    'subtitle' => "{$subjectCount} mapel aktif · {$ungroupedSubjectCount} belum dikelompokkan.",
                    'detail' => 'Perbaiki langsung melalui kartu mapel atau unggah workbook resmi.',
                    'ready' => $subjectCount > 0 && $ungroupedSubjectCount === 0,
                    'url' => AssessmentSubjectResource::canViewAny() ? AssessmentSubjectResource::getUrl() : null,
                    'action' => 'Kelola Mapel',
                ],
                [
                    'number' => '4',
                    'title' => 'Guru–Mapel–Kelas',
                    'subtitle' => "{$teachingCount} penugasan mengajar aktif.",
                    'detail' => 'Penugasan terstruktur dikelola dari tab Penilaian pada Guru & Tendik.',
                    'ready' => $teachingCount > 0,
                    'url' => GuruTendikResource::canViewAny() ? GuruTendikResource::getUrl() : null,
                    'action' => 'Atur Guru Mapel',
                ],
                [
                    'number' => '5',
                    'title' => 'Wali Kelas',
                    'subtitle' => "{$homeroomCount} penugasan wali kelas aktif.",
                    'detail' => 'Setiap kelas periode wajib memiliki tepat satu wali kelas.',
                    'ready' => $homeroomCount > 0,
                    'url' => GuruTendikResource::canViewAny() ? GuruTendikResource::getUrl() : null,
                    'action' => 'Atur Wali Kelas',
                ],
                [
                    'number' => '6',
                    'title' => 'Siswa, Nilai, dan Periode',
                    'subtitle' => "{$studentCount} siswa aktif · {$periodCount} periode.",
                    'detail' => 'Buka periode untuk membekukan siswa, kelas, guru, mapel, dan wali kelas.',
                    'ready' => $studentCount > 0 && $periodCount > 0,
                    'url' => AssessmentPeriodResource::canViewAny() ? AssessmentPeriodResource::getUrl() : null,
                    'action' => 'Periksa Periode',
                ],
                [
                    'number' => '7',
                    'title' => 'Layout, Watermark, dan Preflight',
                    'subtitle' => $activeTemplates->count().' template aktif.',
                    'detail' => 'Preview boleh dibuka kapan saja; PDF resmi hanya dibuat ketika preflight hijau.',
                    'ready' => $activeTemplates->isNotEmpty() && $templateIdentityReady,
                    'url' => AssessmentReportTemplateResource::canViewAny() ? AssessmentReportTemplateResource::getUrl() : null,
                    'action' => 'Atur Template',
                ],
            ],
            'notes' => [
                'Workbook lama tetap diterima, tetapi mapel tanpa kelompok diberi status Belum Dikelompokkan.',
                'Perubahan master tidak mengubah snapshot periode atau rapor yang sudah dibuat.',
                'Pratinjau menampilkan penanda besar ketika data wajib belum lengkap.',
            ],
        ];
    }

    public function getMetrics(): array
    {
        $period = $this->scopePeriods(AssessmentPeriod::query())
            ->find($this->periodId);

        if (! $period) {
            return [
                'period' => null,
                'cards' => [],
                'student_count' => 0,
                'class_count' => 0,
                'assignment_count' => 0,
            ];
        }

        $assignmentQuery = $this->scopeAssignments(
            $period->assignments()->getQuery(),
            (int) $period->getKey(),
        );
        $counts = (clone $assignmentQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $rombelIds = (clone $assignmentQuery)
            ->pluck('assessment_period_rombel_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $cards = collect(AssignmentStatus::cases())
            ->map(fn (AssignmentStatus $status): array => [
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
                'status' => $status->value,
                'url' => $this->statusUrl($period, $status),
            ])
            ->all();

        return [
            'period' => $period,
            'cards' => $cards,
            'student_count' => $period->students()
                ->whereIn('assessment_period_rombel_id', $rombelIds)
                ->where('is_active', true)
                ->count(),
            'class_count' => $rombelIds->count(),
            'assignment_count' => (clone $assignmentQuery)->count(),
        ];
    }

    public function getRecentAuditRows(): array
    {
        $user = auth()->user();

        if (! $user instanceof User
            || (! $user->hasFullAdminAccess() && ! $user->can('penilaian.audit.view'))) {
            return [];
        }

        if (! $this->periodId) {
            return [];
        }

        $scopedPeriodId = $this->scopePeriods(AssessmentPeriod::query())
            ->whereKey($this->periodId)
            ->value('id');

        if (! $scopedPeriodId) {
            return [];
        }

        return AuditLog::query()
            ->where('assessment_period_id', $scopedPeriodId)
            ->with(['actor', 'period'])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => (int) $log->getKey(),
                'event' => (string) $log->event,
                'actor' => (string) ($log->actor?->name ?: 'Sistem'),
                'period' => (string) ($log->period?->name ?: 'Tanpa periode'),
                'subject' => filled($log->subject_type)
                    ? class_basename((string) $log->subject_type).' #'.$log->subject_id
                    : 'Aktivitas sistem',
                'time' => $log->created_at?->format('d/m/Y H:i:s') ?? '-',
                'reason' => filled($log->reason) ? (string) $log->reason : 'Tidak ada alasan tambahan.',
            ])
            ->all();
    }

    public function viewAuditLogAction(): Action
    {
        return Action::make('viewAuditLog')
            ->label('Lihat Detail')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalWidth('5xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalHeading(function (array $arguments): string {
                $record = $this->authorizedAuditLog((int) ($arguments['record'] ?? 0));

                return 'Detail Log - '.$record->event;
            })
            ->modalContent(function (array $arguments) {
                $record = $this->authorizedAuditLog((int) ($arguments['record'] ?? 0));

                return view(
                    'filament.resources.assessment-audit-log-resource.audit-detail',
                    ['record' => $record],
                );
            });
    }

    protected function authorizedAuditLog(int $recordId): AuditLog
    {
        abort_unless($recordId > 0, 404);

        $record = AuditLog::query()
            ->with(['actor', 'period'])
            ->whereKey($recordId)
            ->firstOrFail();

        Gate::authorize('view', $record);

        return $record;
    }

    protected function statusUrl(AssessmentPeriod $period, AssignmentStatus $status): string
    {
        $page = AssessmentPageMap::page(
            AssessmentPageMap::normalizeType($period->type),
            'status',
        );

        return $page::getUrl([
            'period' => $period->getKey(),
            'status' => $status->value,
        ]);
    }

    protected function scopePeriods(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasFullAdminAccess() || $user->can('penilaian.verify') || $user->hasRole('kepala_sekolah')) {
            return $query;
        }

        if (! $user->guru_tendik_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $periods) use ($user): void {
            $periods
                ->whereHas('assignments', fn (Builder $assignments): Builder => $assignments->where('teacher_id', $user->guru_tendik_id))
                ->orWhereHas('homerooms', fn (Builder $homerooms): Builder => $homerooms->where('teacher_id', $user->guru_tendik_id));
        });
    }

    protected function scopeAssignments(Builder $query, int $periodId): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        // Dashboard menerapkan syarat izin 'penilaian.homeroom' untuk kelas wali
        // (perilaku asli dashboard, berbeda dari halaman status pengiriman).
        return app(AssessmentStatusScope::class)->apply($query, $user, $periodId, butuhIzinWaliKelas: true);
    }
}
