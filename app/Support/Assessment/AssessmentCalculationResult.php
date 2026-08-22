<?php

namespace App\Support\Assessment;

final readonly class AssessmentCalculationResult
{
    /**
     * @param  array<int, int|string>  $missingRequiredComponentIds
     * @param  array<string, mixed>  $detail
     */
    public function __construct(
        public ?float $finalScore,
        public ?string $predicate,
        public ?string $description,
        public bool $isComplete,
        public array $missingRequiredComponentIds,
        public array $detail,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toResultAttributes(): array
    {
        return [
            'final_score' => $this->finalScore,
            'predicate' => $this->predicate,
            'description' => $this->description,
            'calculation_detail' => $this->detail,
            'formula_version' => AssessmentCalculator::FORMULA_VERSION,
            'calculated_at' => now(),
        ];
    }
}
