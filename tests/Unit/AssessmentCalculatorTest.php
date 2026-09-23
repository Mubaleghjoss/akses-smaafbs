<?php

namespace Tests\Unit;

use App\Support\Assessment\AssessmentCalculator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssessmentCalculatorTest extends TestCase
{
    public function test_it_calculates_weighted_normalized_score_with_half_up_rounding(): void
    {
        $result = (new AssessmentCalculator)->calculate(
            components: [
                [
                    'id' => 11,
                    'code' => 'TUGAS',
                    'name' => 'Tugas',
                    'domain' => 'Pemahaman konsep',
                    'weight' => 40,
                    'maximum_score' => 80,
                    'is_required' => true,
                ],
                [
                    'id' => 12,
                    'code' => 'TES',
                    'name' => 'Tes',
                    'domain' => 'Penerapan',
                    'weight' => 60,
                    'maximum_score' => 100,
                    'is_required' => true,
                ],
            ],
            scores: [
                ['assessment_component_id' => 11, 'score' => 60],
                ['assessment_component_id' => 12, 'score' => 85],
            ],
            scheme: [
                'minimum_score' => 0,
                'maximum_score' => 100,
                'rounding_precision' => 1,
                'settings' => [
                    'predicates' => [
                        ['label' => 'A', 'minimum_score' => 90],
                        ['label' => 'B', 'minimum_score' => 80],
                        ['label' => 'C', 'minimum_score' => 70],
                    ],
                ],
            ],
        );

        $this->assertTrue($result->isComplete);
        $this->assertSame(81.0, $result->finalScore);
        $this->assertSame('B', $result->predicate);
        $this->assertStringContainsString('Penerapan', $result->description);
        $this->assertStringContainsString('Pemahaman konsep', $result->description);
        $this->assertSame(AssessmentCalculator::FORMULA_VERSION, $result->detail['formula_version']);
        $this->assertSame('PHP_ROUND_HALF_UP', $result->detail['rounding_mode']);
    }

    public function test_null_required_score_stays_null_and_does_not_become_zero(): void
    {
        $result = (new AssessmentCalculator)->calculate(
            components: $this->components(),
            scores: [1 => null, 2 => 90],
            scheme: $this->scheme(),
        );

        $this->assertFalse($result->isComplete);
        $this->assertNull($result->finalScore);
        $this->assertNull($result->predicate);
        $this->assertSame([1], $result->missingRequiredComponentIds);
        $this->assertNull($result->detail['components'][0]['score']);
        $this->assertFalse($result->detail['components'][0]['included']);
    }

    public function test_optional_empty_component_is_excluded_from_denominator(): void
    {
        $components = $this->components();
        $components[1]['is_required'] = false;

        $result = (new AssessmentCalculator)->calculate(
            components: $components,
            scores: [1 => 80, 2 => null],
            scheme: $this->scheme(),
        );

        $this->assertTrue($result->isComplete);
        $this->assertSame(80.0, $result->finalScore);
        $this->assertSame(50.0, $result->detail['included_weight']);
    }

    public function test_score_outside_component_range_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Nilai harus berada pada rentang 0 sampai 100.');

        (new AssessmentCalculator)->calculate(
            components: $this->components(),
            scores: [1 => 101, 2 => 75],
            scheme: $this->scheme(),
        );
    }

    public function test_active_component_weights_must_equal_one_hundred(): void
    {
        $components = $this->components();
        $components[1]['weight'] = 40;

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Total bobot komponen aktif harus tepat 100%.');

        (new AssessmentCalculator)->calculate(
            components: $components,
            scores: [1 => 80, 2 => 75],
            scheme: $this->scheme(),
        );
    }

    public function test_configured_kkm_is_preserved_in_calculation_detail(): void
    {
        $scheme = $this->scheme();
        $scheme['settings'] = ['kkm' => 78];

        $complete = (new AssessmentCalculator)->calculate(
            components: $this->components(),
            scores: [1 => 80, 2 => 76],
            scheme: $scheme,
        );
        $below = (new AssessmentCalculator)->calculate(
            components: $this->components(),
            scores: [1 => 70, 2 => 70],
            scheme: $scheme,
        );

        $this->assertSame(78.0, $complete->detail['kkm']);
        $this->assertTrue($complete->detail['meets_kkm']);
        $this->assertFalse($below->detail['meets_kkm']);
    }

    public function test_asts_calculator_averages_daily_scores_and_applies_default_predicate(): void
    {
        $result = (new AssessmentCalculator)->calculate(
            components: $this->astsComponents(),
            scores: [1 => 98, 2 => 95, 3 => 95, 4 => 96],
            scheme: $this->scheme(),
        );

        $this->assertTrue($result->isComplete);
        $this->assertSame(96.0, $result->finalScore);
        $this->assertSame('A - Sangat Baik', $result->predicate);
        $this->assertSame(96.0, $result->detail['daily_average']);
        $this->assertSame(96.0, $result->detail['pure_asts']);
        $this->assertSame(['daily' => 50.0, 'pure_asts' => 50.0], $result->detail['weights']);
        $this->assertSame('A - Sangat Baik', $result->detail['predicate_label']);
    }

    public function test_asts_calculator_accepts_one_daily_score_and_pure_score(): void
    {
        $result = (new AssessmentCalculator)->calculate(
            components: $this->astsComponents(),
            scores: [1 => 80, 4 => 90],
            scheme: $this->scheme(),
        );

        $this->assertTrue($result->isComplete);
        $this->assertSame(85.0, $result->finalScore);
    }

    public function test_asts_calculator_blocks_final_score_without_any_daily_score_or_pure_score(): void
    {
        $calculator = new AssessmentCalculator;

        $withoutDaily = $calculator->calculate($this->astsComponents(), [4 => 90], $this->scheme());
        $withoutPure = $calculator->calculate($this->astsComponents(), [1 => 90], $this->scheme());

        $this->assertFalse($withoutDaily->isComplete);
        $this->assertNull($withoutDaily->finalScore);
        $this->assertSame([1], $withoutDaily->missingRequiredComponentIds);
        $this->assertFalse($withoutPure->isComplete);
        $this->assertNull($withoutPure->finalScore);
        $this->assertSame([4], $withoutPure->missingRequiredComponentIds);
    }

    public function test_asts_calculator_uses_configured_daily_and_pure_weights(): void
    {
        $scheme = $this->scheme();
        $scheme['settings'] = ['asts' => ['daily_weight' => 40, 'pure_weight' => 60]];

        $result = (new AssessmentCalculator)->calculate(
            components: $this->astsComponents(),
            scores: [1 => 80, 4 => 90],
            scheme: $scheme,
        );

        $this->assertSame(86.0, $result->finalScore);
        $this->assertSame(['daily' => 40.0, 'pure_asts' => 60.0], $result->detail['weights']);
    }

    /** @return array<int, array<string, mixed>> */
    private function astsComponents(): array
    {
        return [
            ['id' => 1, 'code' => 'UH1', 'name' => 'UH 1', 'weight' => 16.6667, 'maximum_score' => 100, 'is_required' => false],
            ['id' => 2, 'code' => 'UH2', 'name' => 'UH 2', 'weight' => 16.6667, 'maximum_score' => 100, 'is_required' => false],
            ['id' => 3, 'code' => 'UH3', 'name' => 'UH 3', 'weight' => 16.6666, 'maximum_score' => 100, 'is_required' => false],
            ['id' => 4, 'code' => 'ASTS_MURNI', 'name' => 'Nilai Murni ASTS', 'weight' => 50, 'maximum_score' => 100, 'is_required' => true],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function components(): array
    {
        return [
            [
                'id' => 1,
                'code' => 'A',
                'name' => 'Komponen A',
                'weight' => 50,
                'maximum_score' => 100,
                'is_required' => true,
            ],
            [
                'id' => 2,
                'code' => 'B',
                'name' => 'Komponen B',
                'weight' => 50,
                'maximum_score' => 100,
                'is_required' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scheme(): array
    {
        return [
            'minimum_score' => 0,
            'maximum_score' => 100,
            'rounding_precision' => 0,
        ];
    }
}
