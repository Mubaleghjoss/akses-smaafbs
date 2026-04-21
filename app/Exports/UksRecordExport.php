<?php

namespace App\Exports;

use App\Models\UksRecord;
use App\Support\Uks\UksRecordSupport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class UksRecordExport implements FromArray, ShouldAutoSize
{
    public function array(): array
    {
        $columns = UksRecordSupport::exportableColumns();
        $rows = [$columns];

        $records = UksRecord::query()
            ->orderByDesc('tanggal_sakit')
            ->orderBy('nama_siswa')
            ->get($columns);

        foreach ($records as $record) {
            $rows[] = array_map(
                fn (string $column): mixed => $record->{$column},
                $columns,
            );
        }

        return $rows;
    }
}
