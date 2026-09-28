<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\HomeroomAssignment;
use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\SubjectCategory;
use App\Models\Assessment\TeachingAssignment;
use App\Models\GuruTendik;
use App\Models\Rombel;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url as UrlAttribute;

/**
 * MATRIKS penugasan: kelas (baris) × mapel (kolom), tiap sel memilih guru.
 *
 * Menggantikan pengisian satu-per-satu yang paling banyak menghabiskan waktu —
 * tabel ini punya baris terbanyak (guru × mapel × rombel). Ditambah tab Wali
 * Kelas dan tombol "Salin dari semester lalu".
 *
 * Snapshot nama (teacher/subject/rombel_name_snapshot) tetap diisi agar riwayat
 * lama tidak berubah saat guru pindah atau mapel diganti nama.
 */
class AssessmentTeachingMatrix extends AssessmentPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Penugasan Guru & Mapel';

    protected static ?string $slug = 'penilaian/matriks-penugasan';

    protected static ?int $navigationSort = 20;

    protected static string $assessmentPermission = 'penilaian.manage';

    protected string $view = 'filament.pages.assessment.teaching-matrix';

    #[UrlAttribute(as: 'semester')]
    public ?int $semesterId = null;

    #[UrlAttribute(as: 'tab')]
    public string $tab = 'mengajar';

    /** matriks[rombelId][subjectId] = teacherId|'' */
    public array $matriks = [];

    /** wali[rombelId] = teacherId|'' */
    public array $wali = [];

    public function mount(): void
    {
        $this->authorizeAssessment('penilaian.manage');

        $this->semesterId ??= Semester::query()->where('is_active', true)->latest('starts_on')->value('id')
            ?? Semester::query()->latest('starts_on')->value('id');

        $this->muatData();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Penugasan Guru & Mapel';
    }

    public function updatedSemesterId(): void
    {
        $this->muatData();
    }

    public function pilihTab(string $tab): void
    {
        $this->tab = in_array($tab, ['mengajar', 'wali'], true) ? $tab : 'mengajar';
    }

    /**
     * Muat keadaan sekarang dari basis data ke dalam matriks.
     */
    public function muatData(): void
    {
        $this->matriks = [];
        $this->wali = [];

        if (! $this->semesterId) {
            return;
        }

        foreach (TeachingAssignment::query()
            ->where('assessment_semester_id', $this->semesterId)
            ->where('is_active', true)
            ->get(['rombel_id', 'assessment_subject_id', 'teacher_id']) as $t) {
            $this->matriks[(int) $t->rombel_id][(int) $t->assessment_subject_id] = (string) $t->teacher_id;
        }

        foreach (HomeroomAssignment::query()
            ->where('assessment_semester_id', $this->semesterId)
            ->where('is_active', true)
            ->get(['rombel_id', 'teacher_id']) as $h) {
            $this->wali[(int) $h->rombel_id] = (string) $h->teacher_id;
        }
    }

    /**
     * @return array<int, array{id: int, nama: string}>
     */
    public function getRombelRows(): array
    {
        return Rombel::query()
            ->where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn (Rombel $r): array => ['id' => (int) $r->getKey(), 'nama' => (string) $r->nama])
            ->all();
    }

    /**
     * @return array<int, array{id: int, nama: string, kelompok: string}>
     */
    public function getSubjectColumns(): array
    {
        return Subject::query()
            ->where('is_active', true)
            ->orderBy('report_group_sort_order')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'report_group_name'])
            ->map(fn (Subject $s): array => [
                'id' => (int) $s->getKey(),
                'nama' => (string) $s->name,
                'kelompok' => (string) ($s->report_group_name ?: 'Lainnya'),
            ])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function getTeacherOptions(): array
    {
        return GuruTendik::query()
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function getSemesterOptions(): array
    {
        return Semester::query()
            ->with('academicYear')
            ->latest('starts_on')
            ->get()
            ->mapWithKeys(fn (Semester $s): array => [
                $s->getKey() => trim(($s->academicYear?->name ?? '-').' · '.$s->name),
            ])
            ->all();
    }

    /**
     * Ringkasan kelengkapan agar admin tahu sisa pekerjaan tanpa menghitung.
     *
     * @return array{total_sel: int, terisi: int, kosong: int, rombel_tanpa_wali: int}
     */
    public function getRingkasan(): array
    {
        $rombel = $this->getRombelRows();
        $mapel = $this->getSubjectColumns();
        $total = count($rombel) * count($mapel);

        $terisi = 0;
        foreach ($rombel as $r) {
            foreach ($mapel as $m) {
                if (filled($this->matriks[$r['id']][$m['id']] ?? null)) {
                    $terisi++;
                }
            }
        }

        $tanpaWali = collect($rombel)
            ->filter(fn (array $r): bool => blank($this->wali[$r['id']] ?? null))
            ->count();

        return [
            'total_sel' => $total,
            'terisi' => $terisi,
            'kosong' => max(0, $total - $terisi),
            'rombel_tanpa_wali' => $tanpaWali,
        ];
    }

    /**
     * Semester lain yang bisa dijadikan sumber salinan.
     *
     * @return array<int|string, string>
     */
    public function getSumberSalinOptions(): array
    {
        return collect($this->getSemesterOptions())
            ->reject(fn (string $label, int|string $id): bool => (int) $id === (int) $this->semesterId)
            ->all();
    }

    public ?int $sumberSalinId = null;

    /**
     * Pratinjau salinan: berapa baris akan disalin, tanpa menyimpan apa pun.
     *
     * @return array{mengajar: int, wali: int, dilewati_guru_hilang: int, tujuan_terisi: bool}|null
     */
    public function getPratinjauSalin(): ?array
    {
        if (! $this->sumberSalinId || ! $this->semesterId) {
            return null;
        }

        $guruAda = GuruTendik::query()->pluck('id')->map(fn ($id): int => (int) $id);

        $mengajar = TeachingAssignment::query()
            ->where('assessment_semester_id', $this->sumberSalinId)
            ->where('is_active', true)
            ->get(['teacher_id']);

        $wali = HomeroomAssignment::query()
            ->where('assessment_semester_id', $this->sumberSalinId)
            ->where('is_active', true)
            ->get(['teacher_id']);

        $hilang = $mengajar->merge($wali)
            ->reject(fn ($r): bool => $guruAda->contains((int) $r->teacher_id))
            ->count();

        return [
            'mengajar' => $mengajar->count(),
            'wali' => $wali->count(),
            'dilewati_guru_hilang' => $hilang,
            'tujuan_terisi' => TeachingAssignment::query()
                ->where('assessment_semester_id', $this->semesterId)
                ->where('is_active', true)
                ->exists(),
        ];
    }

    /**
     * Salin penugasan mengajar DAN wali kelas dari semester lain.
     *
     * Hasilnya tetap dapat diubah. Baris yang gurunya sudah tidak ada dilewati,
     * dan penugasan yang sudah ada di semester tujuan TIDAK ditimpa —
     * updateOrCreate memakai kunci (semester, rombel, mapel).
     */
    public function salinDariSemester(): void
    {
        $this->authorizeAssessment('penilaian.manage');

        if (! $this->sumberSalinId || ! $this->semesterId) {
            Notification::make()->title('Pilih semester sumber lebih dulu')->warning()->send();

            return;
        }

        if ((int) $this->sumberSalinId === (int) $this->semesterId) {
            Notification::make()->title('Semester sumber dan tujuan sama')->warning()->send();

            return;
        }

        $guruAda = GuruTendik::query()->pluck('nama', 'id');
        $namaRombel = Rombel::query()->pluck('nama', 'id');
        $namaMapel = Subject::query()->pluck('name', 'id');

        $disalin = 0;
        $dilewati = 0;
        $waliDisalin = 0;

        DB::transaction(function () use (
            $guruAda, $namaRombel, $namaMapel,
            &$disalin, &$dilewati, &$waliDisalin
        ): void {
            foreach (TeachingAssignment::query()
                ->where('assessment_semester_id', $this->sumberSalinId)
                ->where('is_active', true)
                ->get() as $sumber) {
                if (! $guruAda->has($sumber->teacher_id)) {
                    $dilewati++;

                    continue;
                }

                TeachingAssignment::query()->updateOrCreate(
                    [
                        'assessment_semester_id' => $this->semesterId,
                        'rombel_id' => $sumber->rombel_id,
                        'assessment_subject_id' => $sumber->assessment_subject_id,
                    ],
                    [
                        'assessment_subject_category_id' => $sumber->assessment_subject_category_id,
                        'teacher_id' => $sumber->teacher_id,
                        'teacher_name_snapshot' => $guruAda[$sumber->teacher_id] ?? $sumber->teacher_name_snapshot,
                        'subject_name_snapshot' => $namaMapel[$sumber->assessment_subject_id] ?? $sumber->subject_name_snapshot,
                        'rombel_name_snapshot' => $namaRombel[$sumber->rombel_id] ?? $sumber->rombel_name_snapshot,
                        'is_active' => true,
                    ],
                );
                $disalin++;
            }

            foreach (HomeroomAssignment::query()
                ->where('assessment_semester_id', $this->sumberSalinId)
                ->where('is_active', true)
                ->get() as $sumber) {
                if (! $guruAda->has($sumber->teacher_id)) {
                    $dilewati++;

                    continue;
                }

                HomeroomAssignment::query()->updateOrCreate(
                    [
                        'assessment_semester_id' => $this->semesterId,
                        'rombel_id' => $sumber->rombel_id,
                    ],
                    [
                        'teacher_id' => $sumber->teacher_id,
                        'teacher_name_snapshot' => $guruAda[$sumber->teacher_id] ?? $sumber->teacher_name_snapshot,
                        'rombel_name_snapshot' => $namaRombel[$sumber->rombel_id] ?? $sumber->rombel_name_snapshot,
                        'is_active' => true,
                    ],
                );
                $waliDisalin++;
            }
        });

        $this->muatData();

        Notification::make()
            ->title('Selesai menyalin')
            ->body(sprintf(
                '%d penugasan mengajar dan %d wali kelas disalin.%s Hasilnya masih dapat diubah.',
                $disalin,
                $waliDisalin,
                $dilewati > 0 ? " {$dilewati} baris dilewati karena gurunya sudah tidak ada." : '',
            ))
            ->success()
            ->duration(12000)
            ->send();
    }

    /**
     * Simpan seluruh matriks. Sel kosong menonaktifkan penugasan, TIDAK menghapus
     * barisnya — agar riwayat nilai yang menempel padanya tetap dapat dilacak.
     */
    public function simpan(): void
    {
        $this->authorizeAssessment('penilaian.manage');

        if (! $this->semesterId) {
            Notification::make()->title('Pilih semester lebih dulu')->warning()->send();

            return;
        }

        $guru = GuruTendik::query()->pluck('nama', 'id');
        $namaRombel = Rombel::query()->pluck('nama', 'id');
        $namaMapel = Subject::query()->pluck('name', 'id');
        $kategoriMapel = $this->subjectCategoryMap();

        $tersimpan = 0;
        $dinonaktifkan = 0;
        $waliTersimpan = 0;
        $snapshotDisinkronkan = 0;
        $actor = auth()->user();

        DB::transaction(function () use (
            $guru, $namaRombel, $namaMapel, $kategoriMapel, $actor,
            &$tersimpan, &$dinonaktifkan, &$waliTersimpan, &$snapshotDisinkronkan
        ): void {
            foreach ($this->getRombelRows() as $r) {
                foreach ($this->getSubjectColumns() as $m) {
                    $teacherId = $this->matriks[$r['id']][$m['id']] ?? '';

                    if (blank($teacherId) || ! $guru->has((int) $teacherId)) {
                        $terpengaruh = TeachingAssignment::query()
                            ->where('assessment_semester_id', $this->semesterId)
                            ->where('rombel_id', $r['id'])
                            ->where('assessment_subject_id', $m['id'])
                            ->where('is_active', true)
                            ->update(['is_active' => false]);
                        $dinonaktifkan += $terpengaruh;

                        continue;
                    }

                    $teachingAssignment = $this->simpanPenugasanMengajar(
                        $r,
                        $m,
                        (int) $teacherId,
                        $guru,
                        $namaRombel,
                        $namaMapel,
                        $kategoriMapel,
                    );
                    $snapshotDisinkronkan += $this->sinkronkanSnapshotPeriodeTerbuka(
                        $teachingAssignment,
                        $actor instanceof User ? $actor : null,
                    );
                    $tersimpan++;
                }

                $waliId = $this->wali[$r['id']] ?? '';

                if (blank($waliId) || ! $guru->has((int) $waliId)) {
                    HomeroomAssignment::query()
                        ->where('assessment_semester_id', $this->semesterId)
                        ->where('rombel_id', $r['id'])
                        ->where('is_active', true)
                        ->update(['is_active' => false]);

                    continue;
                }

                HomeroomAssignment::query()->updateOrCreate(
                    [
                        'assessment_semester_id' => $this->semesterId,
                        'rombel_id' => $r['id'],
                    ],
                    [
                        'teacher_id' => (int) $waliId,
                        'teacher_name_snapshot' => $guru[(int) $waliId] ?? null,
                        'rombel_name_snapshot' => $namaRombel[$r['id']] ?? $r['nama'],
                        'is_active' => true,
                    ],
                );
                $waliTersimpan++;
            }
        });

        $this->muatData();

        Notification::make()
            ->title('Penugasan tersimpan')
            ->body(sprintf(
                '%d penugasan mengajar aktif, %d wali kelas.%s',
                $tersimpan,
                $waliTersimpan,
                ($dinonaktifkan > 0 ? " {$dinonaktifkan} penugasan dinonaktifkan (tidak dihapus)." : '')
                    . ($snapshotDisinkronkan > 0 ? " {$snapshotDisinkronkan} snapshot periode terbuka disinkronkan." : ''),
            ))
            ->success()
            ->duration(10000)
            ->send();
    }

    /**
     * @return array<int, int|null>
     */
    private function subjectCategoryMap(): array
    {
        $categoryIds = SubjectCategory::query()->pluck('id', 'code');
        $fallbackId = $categoryIds['WAJIB'] ?? $categoryIds['UMUM-A-LEGACY'] ?? null;

        return Subject::query()
            ->get(['id', 'report_group_code'])
            ->mapWithKeys(fn (Subject $subject): array => [
                (int) $subject->getKey() => $categoryIds[(string) $subject->report_group_code] ?? $fallbackId,
            ])
            ->all();
    }

    /**
     * Simpan satu sel matriks secara deterministik.
     *
     * updateOrCreate lama mencari hanya semester+kelas+mapel. Pada production
     * bisa ada baris lama nonaktif dan baris aktif dengan guru berbeda untuk
     * kombinasi yang sama. Query tanpa urutan dapat mengambil baris lama, lalu
     * mengubah teacher_id ke guru aktif dan menabrak unique key. Karena itu,
     * pilih baris guru yang diminta lebih dulu, lalu nonaktifkan baris aktif
     * lain pada scope semester+kelas+mapel.
     *
     * @param  array{id: int, nama: string}  $rombel
     * @param  array{id: int, nama: string, kelompok: string}  $subject
     * @param  array<int|string, string>  $guru
     * @param  array<int|string, string>  $namaRombel
     * @param  array<int|string, string>  $namaMapel
     * @param  array<int, int|null>  $kategoriMapel
     */
    private function simpanPenugasanMengajar(
        array $rombel,
        array $subject,
        int $teacherId,
        mixed $guru,
        mixed $namaRombel,
        mixed $namaMapel,
        array $kategoriMapel,
    ): TeachingAssignment {
        $scope = TeachingAssignment::query()
            ->where('assessment_semester_id', $this->semesterId)
            ->where('rombel_id', $rombel['id'])
            ->where('assessment_subject_id', $subject['id']);

        $assignment = (clone $scope)->where('teacher_id', $teacherId)->first()
            ?? (clone $scope)->where('is_active', true)->latest('updated_at')->first()
            ?? (clone $scope)->latest('updated_at')->first();

        $values = [
            'assessment_semester_id' => $this->semesterId,
            'rombel_id' => $rombel['id'],
            'assessment_subject_id' => $subject['id'],
            'assessment_subject_category_id' => $kategoriMapel[(int) $subject['id']] ?? null,
            'teacher_id' => $teacherId,
            'teacher_name_snapshot' => $guru[$teacherId] ?? null,
            'subject_name_snapshot' => $namaMapel[$subject['id']] ?? $subject['nama'],
            'rombel_name_snapshot' => $namaRombel[$rombel['id']] ?? $rombel['nama'],
            'is_active' => true,
        ];

        if ($assignment instanceof TeachingAssignment) {
            $assignment->forceFill($values)->save();
        } else {
            $assignment = TeachingAssignment::query()->create($values);
        }

        (clone $scope)
            ->whereKeyNot($assignment->getKey())
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return $assignment;
    }

    /**
     * Update identity snapshots in periods that still accept operational changes.
     * Scores and workflow state are deliberately left untouched, including when
     * an assignment has already been submitted during an open period.
     */
    private function sinkronkanSnapshotPeriodeTerbuka(TeachingAssignment $teaching, ?User $actor): int
    {
        $assignments = AssessmentPeriodAssignment::query()
            ->where('assessment_subject_id', $teaching->assessment_subject_id)
            ->whereHas('period', fn ($query) => $query
                ->where('assessment_semester_id', $teaching->assessment_semester_id)
                ->where('status', AssessmentPeriodStatus::OPEN->value))
            ->whereHas('periodRombel', fn ($query) => $query
                ->where('source_rombel_id', $teaching->rombel_id))
            ->lockForUpdate()
            ->get();

        $updated = 0;
        foreach ($assignments as $assignment) {
            $values = [
                'source_teaching_assignment_id' => $teaching->getKey(),
                'teacher_id' => $teaching->teacher_id,
                'teacher_name_snapshot' => $teaching->teacher_name_snapshot,
            ];
            if ($assignment->only(array_keys($values)) === $values) {
                continue;
            }

            $old = $assignment->only([...array_keys($values), 'lock_version']);
            $assignment->forceFill([
                ...$values,
                'lock_version' => (int) $assignment->lock_version + 1,
            ])->save();

            app(AssessmentAuditLogger::class)->record(
                actor: $actor,
                event: 'assignment.teacher_snapshot_synchronized_from_matrix',
                subject: $assignment,
                oldValues: $old,
                newValues: $assignment->only([...array_keys($values), 'lock_version']),
                reason: 'Perubahan Matriks Penugasan menyelaraskan guru pada periode terbuka tanpa mengubah nilai atau status.',
            );
            $updated++;
        }

        return $updated;
    }

    /**
     * Isi satu kolom mapel dengan guru yang sama untuk SEMUA kelas.
     * Menghemat waktu untuk mapel yang diampu satu guru di semua kelas.
     */
    public function isiKolom(int $subjectId, string $teacherId): void
    {
        foreach ($this->getRombelRows() as $r) {
            $this->matriks[$r['id']][$subjectId] = $teacherId;
        }
    }
}
