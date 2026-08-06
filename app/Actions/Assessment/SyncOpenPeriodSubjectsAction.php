<?php

namespace App\Actions\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\Subject;
use App\Models\Assessment\TeachingAssignment;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use App\Support\Assessment\AssessmentCalculator;
use App\Support\Assessment\AssessmentSchemeResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SyncOpenPeriodSubjectsAction
{
    use AuthorizesAssessmentAction;

    public function __construct(
        private readonly AssessmentSchemeResolver $schemeResolver,
        private readonly AssessmentCalculator $calculator,
        private readonly AssessmentAuditLogger $audit,
    ) {}

    /**
     * @param  array<int, int|string>  $subjectIds
     * @return array<string, mixed>
     */
    public function preview(AssessmentPeriod $period, array $subjectIds, ?int $sourceSchemeId = null): array
    {
        return $this->buildPlan($period, $subjectIds, $sourceSchemeId, false);
    }

    /**
     * @param  array<int, int|string>  $subjectIds
     * @return array<string, mixed>
     */
    public function execute(User $actor, AssessmentPeriod $period, array $subjectIds, ?int $sourceSchemeId = null): array
    {
        $this->authorizePermission($actor, 'penilaian.period.manage');

        return DB::transaction(function () use ($actor, $period, $subjectIds, $sourceSchemeId): array {
            $lockedPeriod = AssessmentPeriod::query()->lockForUpdate()->findOrFail($period->getKey());
            $plan = $this->buildPlan($lockedPeriod, $subjectIds, $sourceSchemeId, true);
            $defaultScheme = $this->ensureDefaultScheme($actor, $lockedPeriod, $plan);
            $summary = [
                'period_id' => (int) $lockedPeriod->getKey(),
                'period_name' => $lockedPeriod->name,
                'subject_count' => $plan['subject_count'],
                'class_count' => $plan['class_count'],
                'plotting_count' => $plan['plotting_count'],
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'retained' => $plan['retained'],
                'default_scheme_id' => (int) $defaultScheme->getKey(),
                'default_scheme_created' => $plan['default_scheme_created'],
                'source_scheme_id' => (int) $plan['source_scheme']->getKey(),
            ];

            /** @var Collection<int, AssessmentPeriodAssignment> $existing */
            $existing = $plan['existing'];
            $existingByKey = $existing->keyBy(fn (AssessmentPeriodAssignment $assignment): string => $this->assignmentKey(
                (int) $assignment->assessment_subject_id,
                (int) $assignment->periodRombel?->source_rombel_id,
            ));

            /** @var TeachingAssignment $master */
            foreach ($plan['desired'] as $master) {
                $subject = $plan['subjects']->get((int) $master->assessment_subject_id);
                $periodRombel = $plan['period_rombels']->get((int) $master->rombel_id);
                $key = $this->assignmentKey((int) $master->assessment_subject_id, (int) $master->rombel_id);
                $assignment = $existingByKey->get($key);
                $values = $this->assignmentValues($master, $subject, $periodRombel);

                if ($assignment) {
                    $old = $assignment->only(array_keys($values));
                    if ($old === $values) {
                        $summary['unchanged']++;

                        continue;
                    }

                    $old['lock_version'] = (int) $assignment->lock_version;
                    $assignment->forceFill([
                        ...$values,
                        'lock_version' => (int) $assignment->lock_version + 1,
                    ])->save();
                    $this->audit->record(
                        actor: $actor,
                        event: 'assignment.teacher_category_synchronized',
                        subject: $assignment,
                        oldValues: $old,
                        newValues: [...$values, 'lock_version' => (int) $assignment->lock_version],
                        reason: 'Sinkronisasi aditif plotting guru-mapel-kelas ke periode terbuka.',
                    );
                    $summary['updated']++;
                } else {
                    $assignment = AssessmentPeriodAssignment::query()->create([
                        'assessment_period_id' => $lockedPeriod->getKey(),
                        'assessment_period_rombel_id' => $periodRombel->getKey(),
                        'assessment_subject_id' => $subject->getKey(),
                        ...$values,
                        'status' => AssignmentStatus::DRAFT,
                        'lock_version' => 1,
                    ]);
                    $this->audit->record(
                        actor: $actor,
                        event: 'assignment.added_from_master',
                        subject: $assignment,
                        newValues: $values,
                        reason: 'Sinkronisasi aditif plotting guru-mapel-kelas ke periode terbuka.',
                    );
                    $summary['created']++;
                }

                $scheme = $this->schemeResolver->forAssignment($assignment->fresh('periodRombel'));
                $this->calculator->calculate($scheme->components, [], $scheme);
            }

            $this->audit->record(
                actor: $actor,
                event: 'period.subject_assignments_bulk_synchronized',
                subject: $lockedPeriod,
                newValues: collect($summary)->except(['period_name'])->all(),
                reason: 'Sinkronisasi bulk mapel aktif ke periode terbuka.',
            );

            return $summary;
        }, 3);
    }

    /**
     * @param  array<int, int|string>  $subjectIds
     * @return array<string, mixed>
     */
    private function buildPlan(AssessmentPeriod $period, array $subjectIds, ?int $sourceSchemeId, bool $lock): array
    {
        if ($period->status !== AssessmentPeriodStatus::OPEN) {
            throw ValidationException::withMessages([
                'period' => 'Sinkronisasi mapel hanya tersedia ketika periode berstatus Terbuka.',
            ]);
        }

        if ((int) $period->assessment_semester_id <= 0) {
            throw ValidationException::withMessages(['period' => 'Semester periode tidak valid.']);
        }

        $ids = collect($subjectIds)->map(fn (mixed $id): int => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['subjects' => 'Pilih minimal satu mapel aktif untuk disinkronkan.']);
        }

        $subjectQuery = Subject::query()->whereIn('id', $ids)->where('is_active', true);
        if ($lock) {
            $subjectQuery->lockForUpdate();
        }
        $subjects = $subjectQuery->get()->keyBy(fn (Subject $subject): int => (int) $subject->getKey());
        if ($subjects->count() !== $ids->count()) {
            throw ValidationException::withMessages(['subjects' => 'Salah satu mapel tidak ditemukan atau sudah tidak aktif. Muat ulang halaman.']);
        }

        $periodRombelQuery = $period->periodRombels()->where('is_active', true);
        if ($lock) {
            $periodRombelQuery->lockForUpdate();
        }
        $periodRombels = $periodRombelQuery->get()->keyBy(fn ($rombel): int => (int) $rombel->source_rombel_id);
        if ($periodRombels->isEmpty()) {
            throw ValidationException::withMessages(['period' => 'Periode belum memiliki kelas aktif. Tambahkan kelas pada periode terlebih dahulu.']);
        }

        $desiredQuery = TeachingAssignment::query()
            ->with(['category', 'teacher.userAccount.roles.permissions', 'teacher.userAccount.permissions'])
            ->where('assessment_semester_id', $period->assessment_semester_id)
            ->whereIn('assessment_subject_id', $subjects->keys())
            ->where('is_active', true)
            ->whereIn('rombel_id', $periodRombels->keys());
        if ($lock) {
            $desiredQuery->lockForUpdate();
        }
        $desired = $desiredQuery->get();

        $missingSubjects = $subjects->keys()->diff($desired->pluck('assessment_subject_id')->map(fn (mixed $id): int => (int) $id)->unique());
        if ($missingSubjects->isNotEmpty()) {
            throw ValidationException::withMessages([
                'assignments' => 'Mapel belum memiliki plotting pada kelas aktif periode: '.$subjects->only($missingSubjects)->pluck('name')->implode(', ').'.',
            ]);
        }

        $duplicates = $desired->groupBy(fn (TeachingAssignment $master): string => $this->assignmentKey(
            (int) $master->assessment_subject_id,
            (int) $master->rombel_id,
        ))->filter(fn (Collection $rows): bool => $rows->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'assignments' => 'Satu mapel dan kelas masih mempunyai lebih dari satu guru pada master. Perbaiki plotting terlebih dahulu.',
            ]);
        }

        foreach ($desired as $master) {
            if (! $master->category) {
                throw ValidationException::withMessages([
                    'assignments' => "Plotting {$master->subject_name_snapshot} · {$master->rombel_name_snapshot} belum memiliki kategori rapor.",
                ]);
            }
            $account = $master->teacher?->userAccount;
            if (! $account instanceof User || ! ($account->hasFullAdminAccess() || ($account->can('penilaian.input') && $account->can('penilaian.submit')))) {
                throw ValidationException::withMessages([
                    'assignments' => "Akun guru {$master->teacher_name_snapshot} untuk {$master->subject_name_snapshot} · {$master->rombel_name_snapshot} belum siap Input dan Kirim Nilai.",
                ]);
            }
        }

        $existingQuery = AssessmentPeriodAssignment::query()
            ->with(['scores:id,assessment_period_assignment_id', 'results:id,assessment_period_assignment_id', 'periodRombel'])
            ->where('assessment_period_id', $period->getKey())
            ->whereIn('assessment_subject_id', $subjects->keys());
        if ($lock) {
            $existingQuery->lockForUpdate();
        }
        $existing = $existingQuery->get();
        $existingByKey = $existing->groupBy(fn (AssessmentPeriodAssignment $assignment): string => $this->assignmentKey(
            (int) $assignment->assessment_subject_id,
            (int) $assignment->periodRombel?->source_rombel_id,
        ));
        if ($existingByKey->contains(fn (Collection $rows): bool => $rows->count() > 1)) {
            throw ValidationException::withMessages([
                'assignments' => 'Periode memiliki assignment ganda untuk mapel dan kelas yang sama. Sinkronisasi dibatalkan.',
            ]);
        }

        $desiredKeys = $desired->mapWithKeys(fn (TeachingAssignment $master): array => [
            $this->assignmentKey((int) $master->assessment_subject_id, (int) $master->rombel_id) => $master,
        ]);
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        foreach ($desiredKeys as $key => $master) {
            $assignment = $existingByKey->get($key)?->first();
            if (! $assignment) {
                $created++;

                continue;
            }

            $subject = $subjects->get((int) $master->assessment_subject_id);
            $periodRombel = $periodRombels->get((int) $master->rombel_id);
            $values = $this->assignmentValues($master, $subject, $periodRombel);
            if ($assignment->only(array_keys($values)) === $values) {
                $unchanged++;

                continue;
            }

            $status = $assignment->status instanceof AssignmentStatus
                ? $assignment->status
                : AssignmentStatus::from((string) $assignment->status);
            if (! $status->isEditable()) {
                throw ValidationException::withMessages([
                    'assignments' => "Assignment {$assignment->subject_name_snapshot} · {$assignment->rombel_name_snapshot} berstatus {$status->label()} dan tidak dapat diperbarui. Kembalikan ke guru terlebih dahulu.",
                ]);
            }
            $updated++;
        }

        $schemePlan = $this->resolveDefaultSchemePlan($period, $sourceSchemeId, $lock);

        return [
            'period_id' => (int) $period->getKey(),
            'period_name' => $period->name,
            'subject_count' => $subjects->count(),
            'class_count' => $desired->pluck('rombel_id')->unique()->count(),
            'plotting_count' => $desired->count(),
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'retained' => $existing->count() - $existing->filter(fn (AssessmentPeriodAssignment $row): bool => $desiredKeys->has($this->assignmentKey(
                (int) $row->assessment_subject_id,
                (int) $row->periodRombel?->source_rombel_id,
            )))->count(),
            'subjects' => $subjects,
            'period_rombels' => $periodRombels,
            'desired' => $desired,
            'existing' => $existing,
            ...$schemePlan,
        ];
    }

    /** @return array{default_scheme:?AssessmentScheme,source_scheme:AssessmentScheme,default_scheme_created:bool} */
    private function resolveDefaultSchemePlan(AssessmentPeriod $period, ?int $sourceSchemeId, bool $lock): array
    {
        if ($sourceSchemeId && ! AssessmentScheme::query()
            ->whereKey($sourceSchemeId)
            ->where('assessment_period_id', $period->getKey())
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages(['scheme' => 'Skema sumber tidak aktif atau bukan milik periode yang dipilih.']);
        }

        $globalQuery = AssessmentScheme::query()
            ->where('assessment_period_id', $period->getKey())
            ->whereNull('assessment_subject_id')
            ->whereNull('source_rombel_id')
            ->whereNull('assessment_period_rombel_id')
            ->where('is_active', true)
            ->with('components');
        if ($lock) {
            $globalQuery->lockForUpdate();
        }
        $globals = $globalQuery->get();
        if ($globals->count() > 1) {
            throw ValidationException::withMessages(['scheme' => 'Periode memiliki lebih dari satu skema default aktif. Nonaktifkan duplikat di Komponen dan Bobot.']);
        }
        if ($globals->count() === 1) {
            $scheme = $globals->first();
            $this->calculator->calculate($scheme->components, [], $scheme);

            return ['default_scheme' => $scheme, 'source_scheme' => $scheme, 'default_scheme_created' => false];
        }

        $activeQuery = AssessmentScheme::query()
            ->where('assessment_period_id', $period->getKey())
            ->where('is_active', true)
            ->with('components');
        if ($lock) {
            $activeQuery->lockForUpdate();
        }
        $activeSchemes = $activeQuery->get();
        if ($activeSchemes->isEmpty()) {
            throw ValidationException::withMessages([
                'scheme' => 'Periode belum memiliki skema penilaian aktif. Buat skema beserta komponen berbobot 100% di Komponen dan Bobot.',
            ]);
        }

        if ($sourceSchemeId) {
            $source = $activeSchemes->firstWhere('id', $sourceSchemeId);
            if (! $source) {
                throw ValidationException::withMessages(['scheme' => 'Skema sumber tidak aktif atau bukan milik periode yang dipilih.']);
            }
        } elseif ($activeSchemes->count() === 1) {
            $source = $activeSchemes->first();
        } else {
            throw ValidationException::withMessages([
                'scheme' => 'Periode memiliki beberapa skema aktif. Pilih salah satu sebagai sumber Skema Default Periode.',
            ]);
        }

        $this->calculator->calculate($source->components, [], $source);

        return ['default_scheme' => null, 'source_scheme' => $source, 'default_scheme_created' => true];
    }

    /** @param array<string, mixed> $plan */
    private function ensureDefaultScheme(User $actor, AssessmentPeriod $period, array $plan): AssessmentScheme
    {
        if ($plan['default_scheme'] instanceof AssessmentScheme) {
            return $plan['default_scheme'];
        }

        /** @var AssessmentScheme $source */
        $source = $plan['source_scheme'];
        $default = AssessmentScheme::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_subject_id' => null,
            'source_rombel_id' => null,
            'assessment_period_rombel_id' => null,
            'name' => Str::limit('Skema Default Periode · '.$source->name, 150, ''),
            'rounding_precision' => $source->rounding_precision,
            'minimum_score' => $source->minimum_score,
            'maximum_score' => $source->maximum_score,
            'settings' => $source->settings,
            'is_active' => true,
        ]);

        foreach ($source->components as $component) {
            $default->components()->create($component->only([
                'code', 'name', 'domain', 'weight', 'maximum_score', 'is_required', 'sort_order', 'score_source', 'settings',
            ]));
        }
        $default->load('components');
        $this->calculator->calculate($default->components, [], $default);
        $this->audit->record(
            actor: $actor,
            event: 'scheme.default_created_from_source',
            subject: $default,
            newValues: [
                'source_scheme_id' => (int) $source->getKey(),
                'component_count' => $default->components->count(),
                'scope' => 'period',
            ],
            reason: 'Fallback bersama untuk sinkronisasi mapel ke periode terbuka.',
        );

        return $default;
    }

    private function assignmentKey(int $subjectId, int $sourceRombelId): string
    {
        return $subjectId.'|'.$sourceRombelId;
    }

    /** @return array<string, mixed> */
    private function assignmentValues(TeachingAssignment $master, Subject $subject, $periodRombel): array
    {
        return [
            'source_teaching_assignment_id' => $master->getKey(),
            'teacher_id' => $master->teacher_id,
            'teacher_name_snapshot' => $master->teacher_name_snapshot,
            'subject_name_snapshot' => $subject->name,
            'subject_group_code_snapshot' => $master->category->code,
            'subject_group_name_snapshot' => $master->category->name,
            'subject_group_sort_order_snapshot' => (int) $master->category->sort_order,
            'subject_sort_order_snapshot' => (int) $subject->sort_order,
            'rombel_name_snapshot' => $periodRombel->rombel_name_snapshot,
        ];
    }
}
