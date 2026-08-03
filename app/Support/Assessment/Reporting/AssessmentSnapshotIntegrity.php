<?php

namespace App\Support\Assessment\Reporting;

use App\Models\Assessment\ReportSnapshot;

final class AssessmentSnapshotIntegrity
{
    public function checksum(array $payload): string
    {
        $encoded = json_encode(
            $this->canonicalize($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );

        return hash('sha256', $encoded);
    }

    public function isValid(ReportSnapshot $snapshot): bool
    {
        $expected = strtolower(trim((string) $snapshot->snapshot_checksum));

        return preg_match('/^[a-f0-9]{64}$/', $expected) === 1
            && hash_equals($expected, $this->checksum(is_array($snapshot->snapshot_data) ? $snapshot->snapshot_data : []));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
