<?php

namespace App\Exports;

use App\Exports\Sheets\ArraySheetExport;
use App\Support\Uks\UksRecordSupport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UksRecordImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ArraySheetExport(UksRecordSupport::templateRows(), 'template_import_uks'),
            new ArraySheetExport(UksRecordSupport::guideRows(), 'panduan'),
        ];
    }
}
