<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class DataSiswaImportReviewExport implements FromArray, ShouldAutoSize
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        protected array $rows,
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $exportRows = [[
            'BARIS',
            'NAMA UPLOAD',
            'NIPD',
            'NISN',
            'KEPRIBADIAN',
            'GAYA BELAJAR',
            'PROFILING',
            'MBTI',
            'STATUS',
            'ALASAN',
            'KANDIDAT MIRIP',
        ]];

        foreach ($this->rows as $row) {
            $candidateOptions = json_decode((string) ($row['candidate_options_json'] ?? '[]'), true);

            $candidates = collect(is_array($candidateOptions) ? $candidateOptions : [])
                ->pluck('label')
                ->implode(' | ');

            $exportRows[] = [
                $row['row_number'] ?? '',
                $row['source_name'] ?? '',
                $row['nipd'] ?? '',
                $row['nisn'] ?? '',
                $row['kepribadian'] ?? '',
                $row['gaya_belajar'] ?? '',
                $row['profiling'] ?? '',
                $row['mbti'] ?? '',
                $row['match_status_label'] ?? '',
                $row['reason'] ?? '',
                $candidates,
            ];
        }

        return $exportRows;
    }
}
