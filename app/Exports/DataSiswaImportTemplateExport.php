<?php

namespace App\Exports;

use App\Exports\Sheets\ArraySheetExport;
use App\Support\DataSiswa\DataSiswaSupport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DataSiswaImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ArraySheetExport(DataSiswaSupport::templateRows(), 'template_import_siswa'),
            new ArraySheetExport(DataSiswaSupport::simpleProfileTemplateRows(), 'template_data_tes_siswa'),
            new ArraySheetExport(DataSiswaSupport::guideRows(), 'panduan'),
        ];
    }
}
