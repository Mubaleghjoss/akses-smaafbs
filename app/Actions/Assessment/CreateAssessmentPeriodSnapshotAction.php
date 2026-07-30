<?php

namespace App\Actions\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssignmentStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\ScoreSource;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\HomeroomAssignment;
use App\Models\Assessment\Semester;
use App\Models\Assessment\TeachingAssignment;
use App\Models\DataSiswa;
use App\Models\Rombel;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use App\Support\Assessment\AssessmentCalculator;
use App\Support\Assessment\AssessmentSchemeResolver;
use App\Support\Assessment\AssessmentWorkflowGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateAssessmentPeriodSnapshotAction
{
    use AuthorizesAssessmentAction;

    public function __construct(
        private readonly AssessmentWorkflowGuard $guard,
        private readonly AssessmentSchemeResolver $schemeResolver,
        private readonly AssessmentCalculator $calculator,
        private readonly AssessmentAuditLogger $audit,
    ) {}

    public function execute(User $actor, AssessmentPeriod $period): AssessmentPeriod
    {
        $this->authorizePermission($actor, 'penilaian.period.manage');

        return DB::transaction(function () use ($actor, $period): AssessmentPeriod {
            /** @var AssessmentPeriod $locked */
            $locked = AssessmentPeriod::query()->lockForUpdate()->findOrFail($period->getKey());

            if ($this->isStatus($locked, AssessmentPeriodStatus::OPEN)) {
                $this->authorize($actor, 'view', $locked);

                return $locked->refresh();
            }

            $this->authorize($actor, 'open', $locked);
            $this->guard->periodStatus(
                $locked,
                [AssessmentPeriodStatus::DRAFT],
                'Snapshot hanya dapat dibuat dari periode berstatus draf.',
            );

            if (! Semester::query()
                ->whereKey($locked->assessment_semester_id)
                ->where('assessment_academic_year_id', $locked->assessment_academic_year_id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'assessment_semester_id' => 'Semester periode tidak termasuk dalam tahun pelajaran yang dipilih.',
                ]);
            }

            $rombelIds = collect(data_get($locked->settings, 'rombel_ids', []))
                ->map(fn (mixed $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values();

            if ($rombelIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'settings.rombel_ids' => 'Pilih minimal satu kelas sebelum membuka periode penilaian.',
                ]);
            }

            /** @var Collection<int, Rombel> $rombels */
            $rombels = Rombel::query()
                ->whereIn('id', $rombelIds)
                ->where('is_active', true)
                ->orderBy('nama')
                ->get();

            if ($rombels->count() !== $rombelIds->count()) {
                throw ValidationException::withMessages([
                    'settings.rombel_ids' => 'Pilihan kelas memuat kelas yang tidak ditemukan atau sudah tidak aktif.',
                ]);
            }

            $studentsByRombel = DataSiswa::query()
                ->where('status', 'aktif')
                ->whereIn('rombel_saat_ini', $rombels->pluck('nama'))
                ->orderBy('nama')
                ->get()
                ->groupBy('rombel_saat_ini');
            $teachingByRombel = TeachingAssignment::query()
                ->where('assessment_semester_id', $locked->assessment_semester_id)
                ->where('is_active', true)
                ->whereIn('rombel_id', $rombelIds)
                ->get()
                ->groupBy('rombel_id');
            $homeroomByRombel = HomeroomAssignment::query()
                ->where('assessment_semester_id', $locked->assessment_semester_id)
                ->where('is_active', true)
                ->whereIn('rombel_id', $rombelIds)
                ->get()
                ->keyBy('rombel_id');
            $teacherIds = $teachingByRombel->flatten()->pluck('teacher_id')
                ->merge($homeroomByRombel->pluck('teacher_id'))
                ->unique()
                ->values();
            $linkedTeacherIds = User::query()
                ->whereIn('guru_tendik_id', $teacherIds)
                ->pluck('guru_tendik_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            foreach ($rombels as $rombel) {
                if ($studentsByRombel->get($rombel->nama, collect())->isEmpty()) {
                    throw ValidationException::withMessages([
                        'students' => "Kelas {$rombel->nama} tidak memiliki siswa aktif.",
                    ]);
                }

                $teachingAssignments = $teachingByRombel->get($rombel->getKey(), collect());

                if ($teachingAssignments->isEmpty()) {
                    throw ValidationException::withMessages([
                        'teaching_assignments' => "Kelas {$rombel->nama} belum memiliki penugasan guru dan mata pelajaran.",
                    ]);
                }

                if (! $homeroomByRombel->has($rombel->getKey())) {
                    throw ValidationException::withMessages([
                        'homeroom_assignments' => "Kelas {$rombel->nama} belum memiliki wali kelas untuk semester ini.",
                    ]);
                }

                $unlinkedTeacher = $teachingAssignments
                    ->pluck('teacher_id')
                    ->push($homeroomByRombel->get($rombel->getKey())->teacher_id)
                    ->first(fn (mixed $teacherId): bool => ! in_array((int) $teacherId, $linkedTeacherIds, true));

                if ($unlinkedTeacher !== null) {
                    throw ValidationException::withMessages([
                        'teacher_accounts' => "Terdapat guru pada kelas {$rombel->nama} yang belum memiliki akun pengguna tertaut.",
                    ]);
                }
            }

            $periodRombels = collect();

            foreach ($rombels as $rombel) {
                $periodRombel = AssessmentPeriodRombel::query()->firstOrCreate(
                    [
                        'assessment_period_id' => $locked->getKey(),
                        'source_rombel_id' => $rombel->getKey(),
                    ],
                    [
                        'rombel_name_snapshot' => $rombel->nama,
                        'grade_level' => $this->gradeLevel($rombel->nama),
                        'is_active' => true,
                    ],
                );
                $periodRombels->put($rombel->getKey(), $periodRombel);

                foreach ($studentsByRombel->get($rombel->nama, collect()) as $student) {
                    AssessmentPeriodStudent::query()->firstOrCreate(
                        [
                            'assessment_period_id' => $locked->getKey(),
                            'student_id' => $student->getKey(),
                        ],
                        [
                            'assessment_period_rombel_id' => $periodRombel->getKey(),
                            'nis_snapshot' => $student->nis,
                            'nisn_snapshot' => $student->nisn,
                            'student_name_snapshot' => $student->nama,
                            'gender_snapshot' => $student->jk,
                            'rombel_name_snapshot' => $rombel->nama,
                            'is_active' => true,
                        ],
                    );
                }

                foreach ($teachingByRombel->get($rombel->getKey(), collect()) as $teaching) {
                    AssessmentPeriodAssignment::query()->firstOrCreate(
                        [
                            'assessment_period_id' => $locked->getKey(),
                            'teacher_id' => $teaching->teacher_id,
                            'assessment_subject_id' => $teaching->assessment_subject_id,
                            'assessment_period_rombel_id' => $periodRombel->getKey(),
                        ],
                        [
                            'source_teaching_assignment_id' => $teaching->getKey(),
                            'teacher_name_snapshot' => $teaching->teacher_name_snapshot,
                            'subject_name_snapshot' => $teaching->subject_name_snapshot,
                            'rombel_name_snapshot' => $rombel->nama,
                            'status' => AssignmentStatus::DRAFT,
                            'lock_version' => 1,
                        ],
                    );
                }

                $homeroom = $homeroomByRombel->get($rombel->getKey());
                AssessmentPeriodHomeroom::query()->firstOrCreate(
                    [
                        'assessment_period_id' => $locked->getKey(),
                        'assessment_period_rombel_id' => $periodRombel->getKey(),
                    ],
                    [
                        'source_homeroom_assignment_id' => $homeroom->getKey(),
                        'teacher_id' => $homeroom->teacher_id,
                        'teacher_name_snapshot' => $homeroom->teacher_name_snapshot,
                        'rombel_name_snapshot' => $rombel->nama,
                    ],
                );
            }

            $locked->assignments()->get()->each(function (AssessmentPeriodAssignment $assignment) use ($locked): void {
                $scheme = $this->schemeResolver->forAssignment($assignment);
                $this->calculator->calculate($scheme->components, [], $scheme);
                $type = $locked->type instanceof AssessmentType
                    ? $locked->type
                    : AssessmentType::from((string) $locked->type);

                if ($type === AssessmentType::ASTS
                    && $scheme->components->contains(
                        fn ($component): bool => $component->score_source === ScoreSource::ASTS_SNAPSHOT,
                    )) {
                    throw ValidationException::withMessages([
                        'components' => 'Komponen referensi ASTS hanya dapat digunakan pada periode ASAS.',
                    ]);
                }
            });

            $oldStatus = $locked->status->value;
            $locked->forceFill(['status' => AssessmentPeriodStatus::OPEN])->save();
            $this->audit->record(
                actor: $actor,
                event: 'period.opened',
                subject: $locked,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => AssessmentPeriodStatus::OPEN->value,
                    'rombel_count' => $periodRombels->count(),
                    'student_count' => $locked->students()->count(),
                    'assignment_count' => $locked->assignments()->count(),
                ],
            );

            return $locked->refresh();
        }, 3);
    }

    private function gradeLevel(string $rombelName): ?string
    {
        preg_match('/^(XII|XI|X)\b/i', trim($rombelName), $matches);

        return isset($matches[1]) ? strtoupper($matches[1]) : null;
    }

    private function isStatus(AssessmentPeriod $period, AssessmentPeriodStatus $status): bool
    {
        return ($period->status instanceof AssessmentPeriodStatus
            ? $period->status
            : AssessmentPeriodStatus::tryFrom((string) $period->status)) === $status;
    }
}
