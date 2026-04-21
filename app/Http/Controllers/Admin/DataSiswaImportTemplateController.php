<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DataSiswaImportTemplateExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataSiswaImportTemplateController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        return Excel::download(
            new DataSiswaImportTemplateExport,
            'template-data-siswa-dan-data-tes.xlsx',
            ExcelFormat::XLSX,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }
}
