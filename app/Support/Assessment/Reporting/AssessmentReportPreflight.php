<?php

namespace App\Support\Assessment\Reporting;

use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\HomeroomReport;
use App\Models\Assessment\ReportTemplate;
use App\Models\Assessment\StudentSubjectResult;
use Illuminate\Support\Collection;
use RuntimeException;

final class AssessmentReportPreflight
{
    /**
     * @param  array<int, int|string>  $periodRombelIds
     * @return array{ready:bool,groups:array<string, array{label:string,issues:array<int, array{code:string,message:string,count:int,samples:array<int,string>}>}>}
     */
    public function inspect(AssessmentPeriod $period, ReportTemplate $template, array $periodRombelIds = []): array
    {
        $selectedRombelIds = collect($periodRombelIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($selectedRombelIds->isEmpty()) {
            $selectedRombelIds = $period->periodRombels()->pluck('id')->map(fn (mixed $id): int => (int) $id);
        }

        $assignments = $period->assignments()
            ->whereIn('assessment_period_rombel_id', $selectedRombelIds)
            ->orderBy('rombel_name_snapshot')
            ->orderBy('subject_name_snapshot')
            ->get();
        $students = $period->students()
            ->where('is_active', true)
            ->whereIn('assessment_period_rombel_id', $selectedRombelIds)
            ->orderBy('rombel_name_snapshot')
            ->orderBy('student_name_snapshot')
            ->get();
        $homerooms = $period->homerooms()
            ->whereIn('assessment_period_rombel_id', $selectedRombelIds)
            ->get();

        $groups = [
            'master' => ['label' => 'Master dan Penugasan', 'issues' => []],
            'academic' => ['label' => 'Nilai Akademik', 'issues' => []],
            'homeroom' => ['label' => 'Data Wali Kelas', 'issues' => []],
            'template' => ['label' => 'Template dan Identitas', 'issues' => []],
        ];

        if ($assignments->isEmpty()) {
            $this->issue($groups, 'master', 'assignments_missing', 'Belum ada penugasan guru–mapel pada kelas terpilih.', 1);
        }

        $incompleteAssignments = $assignments->filter(fn ($assignment): bool => ! $assignment->teacher_id
            || ! $assignment->assessment_subject_id
            || ! $assignment->assessment_period_rombel_id
            || blank($assignment->teacher_name_snapshot)
            || blank($assignment->subject_name_snapshot)
            || blank($assignment->rombel_name_snapshot));
        if ($incompleteAssignments->isNotEmpty()) {
            $this->issue(
                $groups,
                'master',
                'assignments_incomplete',
                'Guru, mapel, atau kelas pada assignment belum terhubung lengkap.',
                $incompleteAssignments->count(),
                $incompleteAssignments
                    ->map(fn ($row): string => ($row->rombel_name_snapshot ?: 'Tanpa kelas').' · '.($row->subject_name_snapshot ?: 'Tanpa mapel'))
                    ->take(8)
                    ->values()
                    ->all(),
            );
        }

        $ungrouped = $assignments->filter(fn ($assignment): bool => blank($assignment->subject_group_code_snapshot)
            || $assignment->subject_group_code_snapshot === 'BELUM'
            || blank($assignment->subject_group_name_snapshot));
        if ($ungrouped->isNotEmpty()) {
            $this->issue(
                $groups,
                'master',
                'subjects_ungrouped',
                'Mapel belum memiliki kelompok rapor.',
                $ungrouped->count(),
                $ungrouped->map(fn ($row): string => "{$row->rombel_name_snapshot} · {$row->subject_name_snapshot}")->unique()->take(8)->values()->all(),
            );
        }

        $unlocked = $assignments->filter(function ($assignment): bool {
            $status = $assignment->status instanceof AssignmentStatus
                ? $assignment->status->value
                : (string) $assignment->status;

            return $status !== AssignmentStatus::LOCKED->value;
        });
        if ($unlocked->isNotEmpty()) {
            $this->issue(
                $groups,
                'academic',
                'assignments_unlocked',
                'Assignment belum dikunci.',
                $unlocked->count(),
                $unlocked->map(fn ($row): string => "{$row->rombel_name_snapshot} · {$row->subject_name_snapshot}")->take(8)->values()->all(),
            );
        }

        if ($homerooms->count() !== $selectedRombelIds->count()) {
            $existingIds = $homerooms->pluck('assessment_period_rombel_id')->map(fn (mixed $id): int => (int) $id);
            $missingNames = $period->periodRombels()
                ->whereIn('id', $selectedRombelIds->diff($existingIds))
                ->pluck('rombel_name_snapshot')
                ->all();
            $this->issue($groups, 'master', 'homerooms_missing', 'Wali kelas belum tersedia.', count($missingNames), $missingNames);
        }

        $resultKeys = StudentSubjectResult::query()
            ->where('assessment_period_id', $period->getKey())
            ->whereIn('assessment_period_student_id', $students->modelKeys())
            ->whereIn('assessment_period_assignment_id', $assignments->modelKeys())
            ->get(['assessment_period_student_id', 'assessment_period_assignment_id', 'final_score'])
            ->keyBy(fn (StudentSubjectResult $result): string => $result->assessment_period_student_id.'|'.$result->assessment_period_assignment_id);
        $assignmentsByRombel = $assignments->groupBy('assessment_period_rombel_id');
        $missingResults = collect();

        foreach ($students as $student) {
            foreach ($assignmentsByRombel->get($student->assessment_period_rombel_id, collect()) as $assignment) {
                $key = $student->getKey().'|'.$assignment->getKey();
                $result = $resultKeys->get($key);
                if (! $result || $result->final_score === null) {
                    $missingResults->push("{$student->rombel_name_snapshot} · {$student->student_name_snapshot} · {$assignment->subject_name_snapshot}");
                }
            }
        }

        if ($missingResults->isNotEmpty()) {
            $this->issue(
                $groups,
                'academic',
                'results_missing',
                'Nilai akhir siswa belum lengkap.',
                $missingResults->count(),
                $missingResults->take(8)->values()->all(),
            );
        }

        $settings = is_array($template->settings) ? $template->settings : [];
        $layout = app(AssessmentReportLayout::class);
        $reports = null;

        if ($layout->requiresAttitudes($settings)) {
            $reports = HomeroomReport::query()
                ->where('assessment_period_id', $period->getKey())
                ->whereIn('assessment_period_student_id', $students->modelKeys())
                ->get()
                ->keyBy('assessment_period_student_id');
            $missingAttitudes = $students
                ->filter(function ($student) use ($reports): bool {
                    $report = $reports->get($student->getKey());

                    return ! $report
                        || blank($report->spiritual_predicate)
                        || blank($report->spiritual_description)
                        || blank($report->social_predicate)
                        || blank($report->social_description);
                })
                ->map(fn ($student): string => "{$student->rombel_name_snapshot} · {$student->student_name_snapshot}");

            if ($missingAttitudes->isNotEmpty()) {
                $this->issue(
                    $groups,
                    'homeroom',
                    'attitudes_missing',
                    'Sikap spiritual atau sosial belum lengkap.',
                    $missingAttitudes->count(),
                    $missingAttitudes->take(8)->values()->all(),
                );
            }
        }

        if ((bool) data_get($period->settings, 'collect_promotion_status', false)
            && $layout->requiresSemesterStatus($settings)) {
            $reports ??= HomeroomReport::query()
                ->where('assessment_period_id', $period->getKey())
                ->whereIn('assessment_period_student_id', $students->modelKeys())
                ->get()
                ->keyBy('assessment_period_student_id');
            $missingSemesterStatus = $students
                ->filter(function ($student) use ($reports): bool {
                    $report = $reports->get($student->getKey());

                    return ! $report || blank($report->promotion_status);
                })
                ->map(fn ($student): string => "{$student->rombel_name_snapshot} · {$student->student_name_snapshot}");

            if ($missingSemesterStatus->isNotEmpty()) {
                $this->issue(
                    $groups,
                    'homeroom',
                    'semester_status_missing',
                    'Status semester atau kenaikan kelas belum lengkap.',
                    $missingSemesterStatus->count(),
                    $missingSemesterStatus->take(8)->values()->all(),
                );
            }
        }

        $historicalTemplateId = (int) (
            data_get($period->settings, '_reporting.published.template_id')
            ?: data_get($period->settings, '_reporting.pending.template_id')
        );
        if (! $template->is_active && $historicalTemplateId !== (int) $template->getKey()) {
            $this->issue(
                $groups,
                'template',
                'template_not_primary',
                'Template ini merupakan arsip dan bukan template utama.',
                1,
            );
        }

        $effectiveDate = $template->effective_from?->startOfDay();
        $referenceDate = $period->report_date?->startOfDay() ?? now()->startOfDay();
        if ($effectiveDate && $effectiveDate->isAfter($referenceDate)) {
            $this->issue(
                $groups,
                'template',
                'template_not_effective',
                'Tanggal berlaku template berada setelah tanggal rapor.',
                1,
            );
        }

        foreach ([
            'school_name' => 'Nama sekolah',
            'principal_name' => 'Nama kepala sekolah',
            'place' => 'Tempat terbit rapor',
        ] as $field => $label) {
            if (blank(data_get($settings, $field))) {
                $this->issue($groups, 'template', 'template_'.$field, "{$label} belum diisi pada template.", 1);
            }
        }

        return [
            'ready' => collect($groups)->every(fn (array $group): bool => $group['issues'] === []),
            'groups' => $groups,
        ];
    }

    /**
     * @param  array<int, int|string>  $periodRombelIds
     */
    public function assertReady(AssessmentPeriod $period, ReportTemplate $template, array $periodRombelIds = []): void
    {
        $result = $this->inspect($period, $template, $periodRombelIds);
        if ($result['ready']) {
            return;
        }

        $messages = collect($result['groups'])
            ->flatMap(fn (array $group): Collection => collect($group['issues']))
            ->map(fn (array $issue): string => $issue['message'].' ('.$issue['count'].')')
            ->take(4)
            ->implode(' ');

        throw new RuntimeException('Data rapor belum lengkap. '.$messages.' Periksa kartu Preflight pada halaman ini.');
    }

    /**
     * @param  array<string, array{label:string,issues:array<int, array{code:string,message:string,count:int,samples:array<int,string>}>}>  $groups
     * @param  array<int, string>  $samples
     */
    private function issue(
        array &$groups,
        string $group,
        string $code,
        string $message,
        int $count,
        array $samples = [],
    ): void {
        $groups[$group]['issues'][] = [
            'code' => $code,
            'message' => $message,
            'count' => max(1, $count),
            'samples' => $samples,
        ];
    }
}
