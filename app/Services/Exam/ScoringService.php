<?php

namespace App\Services\Exam;

use App\Models\Exam\Answer;
use App\Models\Exam\Attempt;
use App\Models\Exam\Question;

class ScoringService
{
    public function score(Question $question, mixed $submitted): array
    {
        if ($question->type === 'essay') {
            return ['is_correct' => null, 'score' => 0.0];
        }

        $expected = $this->normalize($question->answer_key ?? []);
        $actual = $this->normalize(is_array($submitted) ? $submitted : [$submitted]);
        $correct = $expected === $actual;

        return ['is_correct' => $correct, 'score' => $correct ? (float) $question->weight : 0.0];
    }

    public function refreshAttempt(Attempt $attempt): void
    {
        $auto = (float) $attempt->answers()->sum('auto_score');
        $essay = (float) $attempt->answers()->sum('manual_score');
        $pending = $attempt->answers()->whereHas('question', fn ($q) => $q->where('type', 'essay'))->whereNull('manual_score')->exists();
        $attempt->update([
            'auto_score' => $auto,
            'essay_score' => $essay,
            'final_score' => $pending ? null : $auto + $essay,
            'grading_status' => $pending ? 'pending' : 'final',
        ]);
    }

    public function scoreAnswer(Answer $answer): void
    {
        $result = $this->score($answer->question, $answer->answer);
        $answer->update(['is_correct' => $result['is_correct'], 'auto_score' => $result['score']]);
    }

    private function normalize(array $value): array
    {
        $value = array_values(array_map(fn ($item) => trim((string) $item), $value));
        sort($value, SORT_STRING);
        return $value;
    }
}
