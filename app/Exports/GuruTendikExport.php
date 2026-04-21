<?php

namespace App\Exports;

use App\Exports\Sheets\ArraySheetExport;
use App\Models\GuruTendik;
use App\Support\GuruTendik\GuruTendikSupport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GuruTendikExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $columns = GuruTendikSupport::exportableColumns();
        $masterRows = [$columns];

        $records = GuruTendik::query()
            ->with('tugasTambahan')
            ->orderBy('jenis_ptk')
            ->orderBy('nama')
            ->get();

        foreach ($records as $record) {
            $masterRows[] = array_map(
                fn (string $column): mixed => $record->{$column},
                $columns,
            );
        }

        $historyRows = [[
            'guru_tendik_id',
            'nama_guru_tendik',
            'tugas_tambahan',
            'no_sk',
            'tmt',
            'tst',
            'keterangan',
            'created_at',
        ]];

        foreach ($records as $record) {
            foreach ($record->tugasTambahan as $history) {
                $historyRows[] = [
                    $record->id,
                    $record->nama,
                    $history->tugas_tambahan,
                    $history->no_sk,
                    $history->tmt?->format('Y-m-d'),
                    $history->tst?->format('Y-m-d'),
                    $history->keterangan,
                    $history->created_at?->format('Y-m-d H:i:s'),
                ];
            }
        }

        return [
            new ArraySheetExport($masterRows, 'guru_tendik'),
            new ArraySheetExport($historyRows, 'tugas_tambahan'),
        ];
    }
}
