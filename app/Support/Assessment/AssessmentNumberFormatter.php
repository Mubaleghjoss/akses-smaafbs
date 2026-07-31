<?php

namespace App\Support\Assessment;

final class AssessmentNumberFormatter
{
    public static function score(mixed $value, int $maximumDecimals = 2, string $empty = '-'): string
    {
        if ($value === null || $value === '') {
            return $empty;
        }

        if (! is_numeric($value)) {
            return trim((string) $value) !== '' ? (string) $value : $empty;
        }

        $decimals = min(2, max(0, $maximumDecimals));
        $formatted = number_format((float) $value, $decimals, '.', '');

        if ($decimals === 0) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
