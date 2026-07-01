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
    public const DEFAULT_THRESHOLD = 50.0;

    protected const MIN_NORMALIZED_CHARACTERS = 30;

    protected const MIN_WORDS = 5;

    public function analyzeResponse(PerpustakaanLiterasiResponse $response, float $threshold = self::DEFAULT_THRESHOLD): void
    {
        $response->loadMissing(['answers.question']);

        DB::transaction(function () use ($response, $threshold): void {
            PerpustakaanLiterasiSimilarityMatch::query()
                ->withTrashed()
                ->where(function ($query) use ($response): void {
                    $query
                        ->where('later_response_id', $response->getKey())
                        ->orWhere('matched_response_id', $response->getKey());
                })
                ->forceDelete();

            foreach ($response->answers as $answer) {
                $this->analyzeAnswer($response, $answer, $threshold);
            }
        });
    }

    protected function analyzeAnswer(
        PerpustakaanLiterasiResponse $response,
        PerpustakaanLiterasiAnswer $answer,
        float $threshold
    ): void {
        if ($answer->question && ! $answer->question->plagiarismDetectionEnabled()) {
            return;
        }

        $normalizedAnswer = $this->normalizeText((string) $answer->answer_text);
        $answerComparable = $this->isComparable($normalizedAnswer);
        $answerExactComparable = $this->isExactMatchComparable($normalizedAnswer);

        if (! $answerComparable && ! $answerExactComparable) {
            return;
        }

        $candidateAnswers = PerpustakaanLiterasiAnswer::query()
            ->where('question_id', $answer->question_id)
            ->where('id', '!=', $answer->getKey())
            ->whereHas('response', function ($query) use ($response): void {
                $query->where('material_id', $response->material_id);
            })
            ->with('response:id,material_id,student_class_snapshot,submitted_at')
            ->get();

        foreach ($candidateAnswers as $candidateAnswer) {
            $candidateResponse = $candidateAnswer->response;

            if (! $candidateResponse) {
                continue;
            }

            $candidateText = $this->normalizeText((string) $candidateAnswer->answer_text);
            $score = $this->scoreComparableAnswers($normalizedAnswer, $candidateText, $answerComparable);

            if ($score === null || $score < $threshold) {
                continue;
            }

            [$laterResponse, $matchedResponse, $laterAnswer, $matchedAnswer] = $this->orderPair(
                $response,
                $candidateResponse,
                $answer,
                $candidateAnswer,
            );

            PerpustakaanLiterasiSimilarityMatch::query()->updateOrCreate(
                [
                    'later_answer_id' => $laterAnswer->getKey(),
                    'matched_answer_id' => $matchedAnswer->getKey(),
                ],
                [
                    'material_id' => $response->material_id,
                    'question_id' => $answer->question_id,
                    'later_response_id' => $laterResponse->getKey(),
                    'matched_response_id' => $matchedResponse->getKey(),
                    'student_class_snapshot' => $laterResponse->student_class_snapshot,
                    'similarity_score' => round($score, 2),
                    'later_submitted_at' => $laterResponse->submitted_at,
                    'matched_submitted_at' => $matchedResponse->submitted_at,
                ],
            );
        }
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

    protected function scoreComparableAnswers(string $left, string $right, bool $leftComparable): ?float
    {
        if ($this->isExactMatchComparable($left)
            && $this->isExactMatchComparable($right)
            && $left === $right) {
            return 100.0;
        }

        if (! $leftComparable || ! $this->isComparable($right)) {
            return null;
        }

        return $this->similarityScore($left, $right);
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

    /**
     * @return array{0: PerpustakaanLiterasiResponse, 1: PerpustakaanLiterasiResponse, 2: PerpustakaanLiterasiAnswer, 3: PerpustakaanLiterasiAnswer}
     */
    protected function orderPair(
        PerpustakaanLiterasiResponse $leftResponse,
        PerpustakaanLiterasiResponse $rightResponse,
        PerpustakaanLiterasiAnswer $leftAnswer,
        PerpustakaanLiterasiAnswer $rightAnswer
    ): array {
        $leftTime = $leftResponse->submitted_at?->getTimestamp() ?? 0;
        $rightTime = $rightResponse->submitted_at?->getTimestamp() ?? 0;

        $leftIsLater = $leftTime > $rightTime
            || ($leftTime === $rightTime && (int) $leftResponse->getKey() > (int) $rightResponse->getKey());

        return $leftIsLater
            ? [$leftResponse, $rightResponse, $leftAnswer, $rightAnswer]
            : [$rightResponse, $leftResponse, $rightAnswer, $leftAnswer];
    }
}
