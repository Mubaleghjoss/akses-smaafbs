<?php

namespace App\Support\Assessment;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Validation\ValidationException;

final class AssessmentCalculator
{
    public const FORMULA_VERSION = 'weighted-normalized-v1';

    /**
     * Calculate one student's result through the single assessment formula.
     *
     * Components and scores may be Eloquent models or arrays. Scores are keyed
     * by assessment component ID internally, so their input order is irrelevant.
     *
     * @param  iterable<int, array<string, mixed>|object>  $components
     * @param  iterable<int, array<string, mixed>|object|int|float|string|null>  $scores
     * @param  array<string, mixed>|object  $scheme
     */
    public function calculate(iterable $components, iterable $scores, array|object $scheme): AssessmentCalculationResult
    {
        $schemeData = $this->toArray($scheme);
        $schemeMinimum = $this->numeric($schemeData['minimum_score'] ?? 0, 'minimum_score');
        $schemeMaximum = $this->numeric($schemeData['maximum_score'] ?? 100, 'maximum_score');
        $precision = (int) ($schemeData['rounding_precision'] ?? 0);

        if ($precision < 0 || $precision > 4) {
            $this->fail('rounding_precision', 'Presisi pembulatan harus antara 0 dan 4.');
        }

        if ($schemeMaximum <= $schemeMinimum) {
            $this->fail('maximum_score', 'Nilai maksimum skema harus lebih besar daripada nilai minimum.');
        }

        $settings = $this->settings($schemeData['settings'] ?? []);
        $kkm = array_key_exists('kkm', $settings)
            ? $this->numeric($settings['kkm'], 'settings.kkm')
            : null;
        if ($kkm !== null && ($kkm < 0 || $kkm > 100)) {
            $this->fail('settings.kkm', 'KKM harus berada pada rentang 0 sampai 100.');
        }
        $scoreMap = $this->scoreMap($scores);
        $knownComponentIds = [];
        $normalizedComponents = [];
        $configuredWeight = 0.0;

        foreach ($components as $component) {
            $data = $this->toArray($component);
            $componentSettings = $this->settings($data['settings'] ?? []);

            if (($componentSettings['is_active'] ?? true) === false) {
                continue;
            }

            $id = $data['id'] ?? null;

            if ($id === null || $id === '') {
                $this->fail('components', 'Setiap komponen harus memiliki ID yang stabil.');
            }

            $knownComponentIds[(string) $id] = true;
            $weight = $this->numeric($data['weight'] ?? null, "components.{$id}.weight");

            if ($weight < 0) {
                $this->fail("components.{$id}.weight", 'Bobot komponen tidak boleh negatif.');
            }

            $configuredWeight += $weight;
            $minimum = array_key_exists('minimum_score', $componentSettings)
                ? $this->numeric($componentSettings['minimum_score'], "components.{$id}.minimum_score")
                : $schemeMinimum;
            $maximum = array_key_exists('maximum_score', $data) && $data['maximum_score'] !== null
                ? $this->numeric($data['maximum_score'], "components.{$id}.maximum_score")
                : (array_key_exists('maximum_score', $componentSettings)
                    ? $this->numeric($componentSettings['maximum_score'], "components.{$id}.maximum_score")
                    : $schemeMaximum);

            if ($maximum <= 0 || $maximum <= $minimum) {
                $this->fail("components.{$id}.maximum_score", 'Nilai maksimum komponen harus lebih besar daripada nilai minimum.');
            }

            $normalizedComponents[] = [
                'id' => $id,
                'code' => (string) ($data['code'] ?? $id),
                'name' => (string) ($data['name'] ?? $data['code'] ?? $id),
                'domain' => filled($data['domain'] ?? null) ? (string) $data['domain'] : null,
                'weight' => $weight,
                'minimum_score' => $minimum,
                'maximum_score' => $maximum,
                'is_required' => (bool) ($data['is_required'] ?? false),
                'source' => $data['score_source'] ?? 'manual',
            ];
        }

        if ($normalizedComponents === []) {
            $this->fail('components', 'Skema harus memiliki minimal satu komponen aktif.');
        }

        if (abs($configuredWeight - 100.0) > 0.0001) {
            $this->fail('components', 'Total bobot komponen aktif harus tepat 100%.');
        }

        foreach (array_keys($scoreMap) as $scoreComponentId) {
            if (! isset($knownComponentIds[(string) $scoreComponentId])) {
                $this->fail('scores', 'Nilai memuat komponen yang tidak terdaftar pada skema.');
            }
        }

        $missingRequired = [];
        $includedWeight = 0.0;
        $weightedTotal = 0.0;
        $componentDetails = [];

        foreach ($normalizedComponents as $component) {
            $id = $component['id'];
            $score = $scoreMap[(string) $id] ?? null;

            if ($score === '' || $score === null) {
                if ($component['is_required']) {
                    $missingRequired[] = $id;
                }

                $componentDetails[] = [
                    ...$component,
                    'score' => null,
                    'normalized_score' => null,
                    'weighted_contribution' => null,
                    'included' => false,
                ];

                continue;
            }

            $numericScore = $this->numeric($score, "scores.{$id}");

            if ($numericScore < $component['minimum_score'] || $numericScore > $component['maximum_score']) {
                $this->fail(
                    "scores.{$id}",
                    "Nilai harus berada pada rentang {$component['minimum_score']} sampai {$component['maximum_score']}.",
                );
            }

            $normalizedScore = ($numericScore / $component['maximum_score']) * 100;
            $contribution = $normalizedScore * $component['weight'];
            $includedWeight += $component['weight'];
            $weightedTotal += $contribution;

            $componentDetails[] = [
                ...$component,
                'score' => $numericScore,
                'normalized_score' => round($normalizedScore, 6, PHP_ROUND_HALF_UP),
                'weighted_contribution' => round($contribution / 100, 6, PHP_ROUND_HALF_UP),
                'included' => true,
            ];
        }

        $isComplete = $missingRequired === [];
        $unroundedFinal = $isComplete && $includedWeight > 0
            ? $weightedTotal / $includedWeight
            : null;
        $finalScore = $unroundedFinal !== null
            ? round($unroundedFinal, $precision, PHP_ROUND_HALF_UP)
            : null;
        $predicate = $finalScore !== null ? $this->predicate($finalScore, $settings) : null;
        $description = $finalScore !== null ? $this->description($componentDetails, $settings) : null;

        return new AssessmentCalculationResult(
            finalScore: $finalScore,
            predicate: $predicate,
            description: $description,
            isComplete: $isComplete,
            missingRequiredComponentIds: $missingRequired,
            detail: [
                'formula_version' => self::FORMULA_VERSION,
                'complete' => $isComplete,
                'missing_required_component_ids' => $missingRequired,
                'rounding_precision' => $precision,
                'rounding_mode' => 'PHP_ROUND_HALF_UP',
                'scheme_minimum_score' => $schemeMinimum,
                'scheme_maximum_score' => $schemeMaximum,
                'configured_weight' => $configuredWeight,
                'included_weight' => $includedWeight,
                'unrounded_final_score' => $unroundedFinal,
                'kkm' => $kkm,
                'meets_kkm' => $finalScore !== null && $kkm !== null
                    ? $finalScore >= $kkm
                    : null,
                'generated_description' => $description,
                'components' => $componentDetails,
            ],
        );
    }

    /**
     * @param  iterable<int, array<string, mixed>|object|int|float|string|null>  $scores
     * @return array<string, int|float|string|null>
     */
    private function scoreMap(iterable $scores): array
    {
        $map = [];

        foreach ($scores as $key => $score) {
            if (is_array($score) || is_object($score)) {
                $data = $this->toArray($score);
                $componentId = $data['assessment_component_id'] ?? $data['component_id'] ?? $key;
                $value = $data['score'] ?? null;
            } else {
                $componentId = $key;
                $value = $score;
            }

            if ($componentId === null || $componentId === '') {
                $this->fail('scores', 'Setiap nilai harus merujuk ke komponen.');
            }

            $map[(string) $componentId] = $value;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function predicate(float $score, array $settings): ?string
    {
        $rules = collect($settings['predicates'] ?? [])
            ->map(function (mixed $rule): ?array {
                if (! is_array($rule)) {
                    return null;
                }

                $label = trim((string) ($rule['label'] ?? $rule['predicate'] ?? ''));
                $minimum = $rule['minimum_score'] ?? $rule['min'] ?? null;

                if ($label === '' || ! is_numeric($minimum)) {
                    return null;
                }

                return ['label' => $label, 'minimum' => (float) $minimum];
            })
            ->filter()
            ->sortByDesc('minimum')
            ->values();

        foreach ($rules as $rule) {
            if ($score >= $rule['minimum']) {
                return $rule['label'];
            }
        }

        return filled($settings['fallback_predicate'] ?? null)
            ? (string) $settings['fallback_predicate']
            : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $componentDetails
     * @param  array<string, mixed>  $settings
     */
    private function description(array $componentDetails, array $settings): ?string
    {
        $ranked = collect($componentDetails)
            ->filter(fn (array $component): bool => $component['included'])
            ->sortByDesc('normalized_score')
            ->values();

        if ($ranked->isEmpty()) {
            return null;
        }

        $strongest = $ranked->first();
        $weakest = $ranked->last();
        $label = static fn (array $component): string => $component['domain'] ?: $component['name'];
        $strongLabel = $label($strongest);

        if ($ranked->count() === 1 || $strongest['id'] === $weakest['id']) {
            return str_replace(
                ['{strongest}', '{weakest}'],
                [$strongLabel, $strongLabel],
                (string) ($settings['single_component_description']
                    ?? 'Menunjukkan capaian pada {strongest}.'),
            );
        }

        return str_replace(
            ['{strongest}', '{weakest}'],
            [$strongLabel, $label($weakest)],
            (string) ($settings['description_template']
                ?? 'Menunjukkan capaian terbaik pada {strongest} dan perlu meningkatkan {weakest}.'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(mixed $settings): array
    {
        if (is_array($settings)) {
            return $settings;
        }

        if ($settings instanceof Arrayable) {
            return $settings->toArray();
        }

        if (is_string($settings) && $settings !== '') {
            $decoded = json_decode($settings, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(array|object $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        return get_object_vars($value);
    }

    private function numeric(mixed $value, string $field): float
    {
        if (! is_numeric($value)) {
            $this->fail($field, 'Nilai harus berupa angka.');
        }

        return (float) $value;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
