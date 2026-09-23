<?php

namespace App\Support\Assessment;

use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\StudentSubjectResult;

class AssessmentAstsHomeroomRanking
{
    /**
     * @return array{subjects: array<int, string>, rows: array<int, array{student_name:string, nis:string, scores:array<int, float|null>, total:float, average:float|null, completed:int, expected:int, rank:int|null}>}
     */
    public function forClass(int $periodId, int $rombelId): array
    {
        $subjects = AssessmentPeriodAssignment::query()
            ->where('assessment_period_id', $periodId)
            ->where('assessment_period_rombel_id', $rombelId)
            ->orderBy('subject_group_sort_order_snapshot')
            ->orderBy('subject_sort_order_snapshot')
            ->orderBy('subject_name_snapshot')
            ->pluck('subject_name_snapshot', 'id')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();

        $students = AssessmentPeriodStudent::query()
            ->where('assessment_period_id', $periodId)
            ->where('assessment_period_rombel_id', $rombelId)
            ->where('is_active', true)
            ->orderBy('student_name_snapshot')
            ->get(['id', 'student_name_snapshot', 'nis_snapshot', 'nisn_snapshot']);

        $results = StudentSubjectResult::query()
            ->where('assessment_period_id', $periodId)
            ->whereIn('assessment_period_student_id', $students->modelKeys())
            ->whereIn('assessment_period_assignment_id', array_keys($subjects))
            ->whereNotNull('final_score')
            ->get(['assessment_period_student_id', 'assessment_period_assignment_id', 'final_score'])
            ->keyBy(fn (StudentSubjectResult $result): string => $result->assessment_period_student_id.'-'.$result->assessment_period_assignment_id);

        $rows = [];
        foreach ($students as $student) {
            $scores = [];
            foreach (array_keys($subjects) as $assignmentId) {
                $result = $results->get($student->getKey().'-'.$assignmentId);
                $scores[$assignmentId] = $result ? (float) $result->final_score : null;
            }

            $completedScores = array_values(array_filter($scores, fn (?float $score): bool => $score !== null));
            $completed = count($completedScores);
            $rows[(int) $student->getKey()] = [
                'student_name' => (string) $student->student_name_snapshot,
                'nis' => (string) ($student->nis_snapshot ?: $student->nisn_snapshot ?: '-'),
                'scores' => $scores,
                'total' => array_sum($completedScores),
                'average' => $completed > 0 ? array_sum($completedScores) / $completed : null,
                'completed' => $completed,
                'expected' => count($subjects),
                'rank' => null,
            ];
        }

        uasort($rows, function (array $left, array $right): int {
            if ($left['average'] === null && $right['average'] !== null) {
                return 1;
            }
            if ($left['average'] !== null && $right['average'] === null) {
                return -1;
            }
            if ($left['average'] !== $right['average']) {
                return ($right['average'] ?? 0) <=> ($left['average'] ?? 0);
            }

            return $left['student_name'] <=> $right['student_name'];
        });

        $previousAverage = null;
        $rank = 0;
        $position = 0;
        foreach ($rows as &$row) {
            if ($row['average'] === null) {
                continue;
            }

            $position++;
            if ($previousAverage === null || abs($row['average'] - $previousAverage) > 0.00001) {
                $rank = $position;
                $previousAverage = $row['average'];
            }
            $row['rank'] = $rank;
        }
        unset($row);

        return ['subjects' => $subjects, 'rows' => $rows];
    }
}
