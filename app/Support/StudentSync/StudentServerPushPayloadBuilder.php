<?php

namespace App\Support\StudentSync;

use App\Models\DataSiswa;
use Illuminate\Support\Facades\Schema;

class StudentServerPushPayloadBuilder
{
    /**
     * @param  array<int, int>|null  $studentIds
     * @return array{payload_checksum: string, students: array<int, array<string, mixed>>}
     */
    public function build(?array $studentIds = null): array
    {
        $columns = Schema::getColumnListing('data_siswa');
        $denied = [...config('student_sync.denied_fields', []), 'id'];
        $fieldColumns = array_values(array_diff($columns, $denied));

        $query = DataSiswa::query()->where('status', 'aktif')->orderBy('id');

        if ($studentIds !== null) {
            $ids = array_values(array_unique(array_filter($studentIds, static fn (mixed $id): bool => is_int($id) || ctype_digit((string) $id))));
            $query->whereIn('id', $ids);
        }

        $students = $query->get()->map(function (DataSiswa $student) use ($fieldColumns): array {
            $attributes = $student->getAttributes();
            $fields = $this->nonEmptyValues($attributes, $fieldColumns);
            $identity = $this->nonEmptyValues($attributes, [
                'nipd', 'nisn', 'billing_code', 'nama', 'tanggal_lahir',
            ]);
            $context = ['origin' => 'data_siswa'];

            if (array_key_exists('rombel_saat_ini', $fields)) {
                $context = ['rombel_saat_ini' => $fields['rombel_saat_ini'], ...$context];
            }

            $source = [
                'source_id' => $student->getKey(),
                'identity' => $identity,
                'fields' => $fields,
            ];

            return [
                ...$source,
                'source_checksum' => $this->checksum($source),
                'context' => $context,
            ];
        })->all();

        return [
            'payload_checksum' => $this->checksum($students),
            'students' => $students,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $columns
     * @return array<string, mixed>
     */
    private function nonEmptyValues(array $attributes, array $columns): array
    {
        $values = [];

        foreach ($columns as $column) {
            if (! array_key_exists($column, $attributes) || ! $this->isNonEmpty($attributes[$column])) {
                continue;
            }

            $values[$column] = $attributes[$column];
        }

        return $values;
    }

    private function isNonEmpty(mixed $value): bool
    {
        return $value !== null && (! is_string($value) || trim($value) !== '');
    }

    private function checksum(mixed $value): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
