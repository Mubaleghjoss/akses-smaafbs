<?php

namespace App\Exports;

use App\Exports\Sheets\ArraySheetExport;
use App\Support\GuruTendik\GuruTendikSupport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GuruTendikImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ArraySheetExport(GuruTendikSupport::templateRows(), 'template_import_guru_tendik'),
            new ArraySheetExport(GuruTendikSupport::guideRows(), 'panduan'),
        ];
    }
}
