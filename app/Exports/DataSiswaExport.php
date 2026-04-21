<?php

namespace App\Exports;

use App\Exports\Sheets\ArraySheetExport;
use App\Models\DataSiswa;
use App\Models\User;
use App\Support\DataSiswa\DataSiswaSupport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DataSiswaExport implements WithMultipleSheets
{
    public function __construct(
        protected ?User $user = null,
    ) {}

    public function sheets(): array
    {
        return [
            new ArraySheetExport($this->fullDataRows(), 'data_siswa_lengkap'),
            new ArraySheetExport(DataSiswaSupport::simpleProfileExportRows($this->user), 'profil_sederhana'),
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function fullDataRows(): array
    {
        $columns = DataSiswaSupport::exportableColumns();
        $rows = [$columns];

        $students = DataSiswa::applyVisibleScope(DataSiswa::query(), $this->user)
            ->orderBy('rombel_saat_ini')
            ->orderBy('nama')
            ->get($columns);

        foreach ($students as $student) {
            $rows[] = array_map(
                fn (string $column): mixed => $student->{$column} ?? '',
                $columns,
            );
        }

        return $rows;
    }
}
