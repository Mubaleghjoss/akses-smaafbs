<?php

namespace App\Support\Assessment\Reporting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OnlineExamDemoReportMarker
{
    private const DEMO_CODE = 'DEMO-UJIAN-MVP-2026';

    /**
     * Adds a display-only provenance marker. It deliberately never changes a
     * calculated assessment result or an existing report score.
     *
     * @param array<int, array<string, mixed>> $subjects
     * @return array<int, array<string, mixed>>
     */
    public function annotate(array $subjects, int $studentId, string $className, string $periodType): array
    {
        if (strtolower($periodType) !== 'asts' || ! $this->examTablesReady()) {
            return $subjects;
        }

        $attempts = DB::table('exam_attempts as attempts')
            ->join('exam_student_tokens as tokens', 'tokens.id', '=', 'attempts.student_token_id')
            ->join('exam_schedules as schedules', 'schedules.id', '=', 'attempts.schedule_id')
            ->join('exam_question_sets as sets', 'sets.id', '=', 'schedules.question_set_id')
            ->where('tokens.student_id', $studentId)
            ->where('schedules.exam_code', self::DEMO_CODE)
            ->where('attempts.status', 'submitted')
            ->whereNotNull('attempts.final_score')
            ->select(['sets.subject', 'schedules.class_name', 'attempts.final_score'])
            ->get();

        foreach ($subjects as &$subject) {
            $match = $attempts->first(function (object $attempt) use ($subject, $className): bool {
                return $this->same($attempt->class_name, $className)
                    && $this->same($attempt->subject, data_get($subject, 'name'));
            });

            if ($match) {
                $subject['online_exam_demo'] = [
                    'label' => 'Murni Ujian',
                    'score' => (float) $match->final_score,
                ];
            }
        }
        unset($subject);

        return $subjects;
    }

    private function examTablesReady(): bool
    {
        return collect(['exam_attempts', 'exam_student_tokens', 'exam_schedules', 'exam_question_sets'])
            ->every(fn (string $table): bool => Schema::hasTable($table));
    }

    private function same(mixed $left, mixed $right): bool
    {
        return mb_strtolower(trim((string) $left)) === mb_strtolower(trim((string) $right));
    }
}
