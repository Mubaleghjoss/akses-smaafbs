<?php

namespace App\Exports;

use App\Exports\Sheets\ArraySheetExport;
use App\Models\User;
use App\Support\DataSiswa\DataSiswaSupport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DataSiswaImportTemplateExport implements WithMultipleSheets
{
    public function __construct(protected ?User $user = null) {}

    public function sheets(): array
    {
        return [
            new ArraySheetExport((new DataSiswaExport($this->user))->sheets()[0]->array(), 'template_import_siswa'),
            new ArraySheetExport(DataSiswaSupport::simpleProfileTemplateRows(), 'template_data_tes_siswa'),
            new ArraySheetExport(DataSiswaSupport::guideRows(), 'panduan'),
        ];
    }
}
