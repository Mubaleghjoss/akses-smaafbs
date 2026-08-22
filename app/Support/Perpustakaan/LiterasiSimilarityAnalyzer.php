<?php

namespace App\Support\Perpustakaan;

use App\Models\PerpustakaanLiterasiAnswer;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSimilarityMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LiterasiSimilarityAnalyzer
{
    public const DEFAULT_THRESHOLD = 80.0;

    protected const MIN_NORMALIZED_CHARACTERS = 30;

    protected const MIN_WORDS = 5;

    /**
     * @return array{answers:int,indications:int,candidates:int,below_threshold:int,answer_key_exclusions:int}
     */
    public function analyzeResponse(PerpustakaanLiterasiResponse $response, ?float $threshold = null): array
    {
        $response->loadMissing(['answers.question']);
        $threshold = $this->threshold($threshold);
        $summary = $this->emptySummary();

        foreach ($response->answers as $answer) {
            $result = DB::transaction(function () use ($response, $answer, $threshold): array {
                $lockedAnswer = PerpustakaanLiterasiAnswer::query()
                    ->whereKey($answer->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $lockedAnswer) {
                    return $this->emptySummary();
                }

                $lockedAnswer->setRelation('question', $answer->question);
                $existingMatches = PerpustakaanLiterasiSimilarityMatch::query()
                    ->withTrashed()
                    ->where('later_answer_id', $lockedAnswer->getKey())
                    ->get();
                $evaluation = $this->evaluateAnswer($response, $lockedAnswer, $threshold);

                PerpustakaanLiterasiSimilarityMatch::query()
                    ->withTrashed()
                    ->where('later_answer_id', $lockedAnswer->getKey())
                    ->forceDelete();

                if ($evaluation['match'] !== null) {
                    $candidate = $evaluation['match']['answer'];
                    $candidateResponse = $evaluation['match']['response'];
                    $review = $this->preservedReviewPayload(
                        $existingMatches->firstWhere('matched_answer_id', $candidate->getKey()),
                        $lockedAnswer,
                        $candidate,
                    );

                    PerpustakaanLiterasiSimilarityMatch::query()->create(array_merge([
                        'material_id' => $response->material_id,
                        'question_id' => $lockedAnswer->question_id,
                        'later_response_id' => $response->getKey(),
                        'matched_response_id' => $candidateResponse->getKey(),
                        'later_answer_id' => $lockedAnswer->getKey(),
                        'matched_answer_id' => $candidate->getKey(),
                        'student_class_snapshot' => $response->student_class_snapshot,
                        'similarity_score' => round($evaluation['match']['score'], 2),
                        'later_submitted_at' => $response->submitted_at,
                        'matched_submitted_at' => $candidateResponse->submitted_at,
                    ], $review));
                }

                return $this->summaryFromEvaluation($evaluation);
            }, 3);

            $summary = $this->mergeSummaries($summary, $result);
        }

        return $summary;
    }

    /**
     * @return array{answers:int,indications:int,candidates:int,below_threshold:int,answer_key_exclusions:int}
     */
    public function previewResponse(PerpustakaanLiterasiResponse $response, ?float $threshold = null): array
    {
        $response->loadMissing(['answers.question']);
        $threshold = $this->threshold($threshold);
        $summary = $this->emptySummary();

        foreach ($response->answers as $answer) {
            $summary = $this->mergeSummaries(
                $summary,
                $this->summaryFromEvaluation($this->evaluateAnswer($response, $answer, $threshold)),
            );
        }

        return $summary;
    }

    public function threshold(?float $threshold = null): float
    {
        $configured = $threshold ?? (float) config('literacy.similarity_threshold', self::DEFAULT_THRESHOLD);

        return min(100.0, max(0.0, $configured));
    }

    /**
     * @return array{
     *   match:?array{answer:PerpustakaanLiterasiAnswer,response:PerpustakaanLiterasiResponse,score:float},
     *   candidates:int,
     *   below_threshold:int,
     *   answer_key_exclusions:int
     * }
     */
    protected function evaluateAnswer(
        PerpustakaanLiterasiResponse $response,
        PerpustakaanLiterasiAnswer $answer,
        float $threshold,
    ): array {
        $question = $answer->question;
        $result = [
            'match' => null,
            'candidates' => 0,
            'below_threshold' => 0,
            'answer_key_exclusions' => 0,
        ];

        if (! $question || ! $question->isEssay() || ! $question->plagiarismDetectionEnabled()) {
            return $result;
        }

        if ($question->matchesAnswerKey((string) $answer->answer_text)) {
            $result['answer_key_exclusions'] = 1;

            return $result;
        }

        $normalizedAnswer = $this->normalizeText((string) $answer->answer_text);
        $answerComparable = $this->isComparable($normalizedAnswer);

        if (! $answerComparable && ! $this->isExactMatchComparable($normalizedAnswer)) {
            return $result;
        }

        $candidateAnswers = PerpustakaanLiterasiAnswer::query()
            ->where('question_id', $answer->question_id)
            ->where('id', '!=', $answer->getKey())
            ->whereHas('response', function ($query) use ($response): void {
                $query
                    ->where('material_id', $response->material_id)
                    ->whereNull('deleted_at');
            })
            ->with('response:id,material_id,student_class_snapshot,submitted_at,created_at')
            ->get();

        foreach ($candidateAnswers as $candidateAnswer) {
            $candidateResponse = $candidateAnswer->response;

            if (! $candidateResponse || ! $this->isEarlierResponse($candidateResponse, $response)) {
                continue;
            }

            $result['candidates']++;

            if ($question->matchesAnswerKey((string) $candidateAnswer->answer_text)) {
                $result['answer_key_exclusions']++;

                continue;
            }

            $candidateText = $this->normalizeText((string) $candidateAnswer->answer_text);
            $score = $this->scoreComparableAnswers(
                $normalizedAnswer,
                $candidateText,
                $answerComparable,
                $threshold,
            );

            if ($score === null || $score < $threshold) {
                $result['below_threshold']++;

                continue;
            }

            if ($this->isBetterMatch($score, $candidateResponse, $result['match'])) {
                $result['match'] = [
                    'answer' => $candidateAnswer,
                    'response' => $candidateResponse,
                    'score' => $score,
                ];
            }
        }

        return $result;
    }

    protected function normalizeText(string $text): string
    {
        $text = Str::of(strip_tags($text))->lower()->squish()->toString();
        $text = preg_replace('/[^\pL\pN\s]+/u', ' ', $text) ?? $text;

        return Str::of($text)->squish()->toString();
    }

    protected function isComparable(string $text): bool
    {
        if (mb_strlen($text) < self::MIN_NORMALIZED_CHARACTERS) {
            return false;
        }

        return $this->wordCount($text) >= self::MIN_WORDS;
    }

    protected function isExactMatchComparable(string $text): bool
    {
        return $text !== '';
    }

    protected function scoreComparableAnswers(
        string $left,
        string $right,
        bool $leftComparable,
        float $threshold,
    ): ?float {
        if ($this->isExactMatchComparable($left)
            && $this->isExactMatchComparable($right)
            && $left === $right) {
            return 100.0;
        }

        if (! $leftComparable || ! $this->isComparable($right)) {
            return null;
        }

        if ($this->maximumPossibleSimilarity($left, $right) < $threshold) {
            return null;
        }

        return $this->similarityScore($left, $right);
    }

    protected function maximumPossibleSimilarity(string $left, string $right): float
    {
        $leftLength = mb_strlen($left);
        $rightLength = mb_strlen($right);
        $totalLength = $leftLength + $rightLength;

        if ($totalLength === 0) {
            return 0.0;
        }

        return (200.0 * min($leftLength, $rightLength)) / $totalLength;
    }

    protected function similarityScore(string $left, string $right): float
    {
        similar_text($left, $right, $percent);

        return (float) $percent;
    }

    protected function wordCount(string $text): int
    {
        return Collection::make(preg_split('/\s+/u', $text) ?: [])
            ->filter()
            ->count();
    }

    protected function isEarlierResponse(
        PerpustakaanLiterasiResponse $candidate,
        PerpustakaanLiterasiResponse $response,
    ): bool {
        $candidateTime = $candidate->submitted_at?->getTimestamp() ?? 0;
        $responseTime = $response->submitted_at?->getTimestamp() ?? 0;

        return $candidateTime < $responseTime
            || ($candidateTime === $responseTime && (int) $candidate->getKey() < (int) $response->getKey());
    }

    /**
     * @param  ?array{answer:PerpustakaanLiterasiAnswer,response:PerpustakaanLiterasiResponse,score:float}  $current
     */
    protected function isBetterMatch(
        float $score,
        PerpustakaanLiterasiResponse $candidate,
        ?array $current,
    ): bool {
        if ($current === null || $score > $current['score']) {
            return true;
        }

        if ($score < $current['score']) {
            return false;
        }

        return $this->isEarlierResponse($candidate, $current['response']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function preservedReviewPayload(
        ?PerpustakaanLiterasiSimilarityMatch $existing,
        PerpustakaanLiterasiAnswer $answer,
        PerpustakaanLiterasiAnswer $candidate,
    ): array {
        if (! $existing
            || $existing->review_status === PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED
            || ! $existing->reviewed_at
            || ($answer->updated_at && $answer->updated_at->isAfter($existing->reviewed_at))
            || ($candidate->updated_at && $candidate->updated_at->isAfter($existing->reviewed_at))) {
            return [
                'review_status' => PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ];
        }

        return [
            'review_status' => $existing->review_status,
            'reviewed_by' => $existing->reviewed_by,
            'reviewed_at' => $existing->reviewed_at,
            'review_note' => $existing->review_note,
        ];
    }

    /**
     * @param  array{match:mixed,candidates:int,below_threshold:int,answer_key_exclusions:int}  $evaluation
     * @return array{answers:int,indications:int,candidates:int,below_threshold:int,answer_key_exclusions:int}
     */
    protected function summaryFromEvaluation(array $evaluation): array
    {
        return [
            'answers' => 1,
            'indications' => $evaluation['match'] === null ? 0 : 1,
            'candidates' => $evaluation['candidates'],
            'below_threshold' => $evaluation['below_threshold'],
            'answer_key_exclusions' => $evaluation['answer_key_exclusions'],
        ];
    }

    /**
     * @return array{answers:int,indications:int,candidates:int,below_threshold:int,answer_key_exclusions:int}
     */
    protected function emptySummary(): array
    {
        return [
            'answers' => 0,
            'indications' => 0,
            'candidates' => 0,
            'below_threshold' => 0,
            'answer_key_exclusions' => 0,
        ];
    }

    /**
     * @param  array{answers:int,indications:int,candidates:int,below_threshold:int,answer_key_exclusions:int}  $left
     * @param  array{answers:int,indications:int,candidates:int,below_threshold:int,answer_key_exclusions:int}  $right
     * @return array{answers:int,indications:int,candidates:int,below_threshold:int,answer_key_exclusions:int}
     */
    protected function mergeSummaries(array $left, array $right): array
    {
        foreach (array_keys($left) as $key) {
            $left[$key] += $right[$key];
        }

        return $left;
    }
}
