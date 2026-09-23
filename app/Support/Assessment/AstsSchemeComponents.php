<?php

namespace App\Support\Assessment;

use App\Enums\Assessment\ScoreSource;
use App\Models\Assessment\AssessmentComponent;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\AssessmentScore;

final class AstsSchemeComponents
{
    /** @var list<array{code: string, name: string, weight: float, is_required: bool}> */
    private const STANDARD_COMPONENTS = [
        ['code' => 'UH1', 'name' => 'Ujian Harian 1', 'weight' => 16.6667, 'is_required' => false],
        ['code' => 'UH2', 'name' => 'Ujian Harian 2', 'weight' => 16.6667, 'is_required' => false],
        ['code' => 'UH3', 'name' => 'Ujian Harian 3', 'weight' => 16.6666, 'is_required' => false],
        ['code' => 'ASTS_MURNI', 'name' => 'Nilai Murni ASTS', 'weight' => 50.0, 'is_required' => true],
    ];

    public function isStandard(AssessmentScheme $scheme): bool
    {
        $components = $scheme->relationLoaded('components')
            ? $scheme->components
            : $scheme->components()->get();

        return $components->filter(fn (AssessmentComponent $component): bool => data_get($component->settings, 'is_active', true) !== false)
            ->sortBy('sort_order')
            ->values()
            ->map(fn (AssessmentComponent $component): array => [
                'code' => $component->code,
                'name' => $component->name,
                'weight' => (float) $component->weight,
                'is_required' => (bool) $component->is_required,
                'score_source' => $component->score_source instanceof ScoreSource
                    ? $component->score_source->value
                    : (string) $component->score_source,
            ])
            ->all() === array_map(fn (array $component): array => $component + ['score_source' => ScoreSource::MANUAL->value], self::STANDARD_COMPONENTS);
    }

    public function canNormalize(AssessmentScheme $scheme): bool
    {
        return ! AssessmentScore::query()
            ->whereIn('assessment_component_id', $scheme->components()->select('id'))
            ->exists();
    }

    /**
     * Makes an untouched ASTS scheme use the four canonical manual fields.
     * Existing score-bearing schemes are deliberately left unchanged.
     */
    public function normalize(AssessmentScheme $scheme): bool
    {
        $scheme->load('components');

        if ($this->isStandard($scheme)) {
            return false;
        }

        if (! $this->canNormalize($scheme)) {
            return false;
        }

        $available = $scheme->components->values();
        foreach (self::STANDARD_COMPONENTS as $order => $definition) {
            $component = $available->shift() ?: new AssessmentComponent([
                'assessment_scheme_id' => $scheme->getKey(),
            ]);
            $settings = is_array($component->settings) ? $component->settings : [];
            $settings['is_active'] = true;

            $component->fill([
                'assessment_scheme_id' => $scheme->getKey(),
                'code' => $definition['code'],
                'name' => $definition['name'],
                'domain' => $definition['name'],
                'weight' => $definition['weight'],
                'maximum_score' => 100,
                'is_required' => $definition['is_required'],
                'sort_order' => $order + 1,
                'score_source' => ScoreSource::MANUAL,
                'settings' => $settings,
            ])->save();
        }

        // Retain surplus configuration for auditability without letting it affect ASTS.
        $available->each(function (AssessmentComponent $component): void {
            $settings = is_array($component->settings) ? $component->settings : [];
            $settings['is_active'] = false;
            $component->forceFill(['settings' => $settings])->save();
        });

        $settings = is_array($scheme->settings) ? $scheme->settings : [];
        $settings['asts'] = ['daily_weight' => 50, 'pure_weight' => 50];
        $scheme->forceFill(['settings' => $settings])->save();
        $scheme->load('components');

        return true;
    }
}
