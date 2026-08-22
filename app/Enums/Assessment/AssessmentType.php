<?php

namespace App\Enums\Assessment;

enum AssessmentType: string
{
    case ASTS = 'asts';
    case ASAS = 'asas';

    public function label(): string
    {
        return strtoupper($this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
