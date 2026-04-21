<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ProkerImportTemplateExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProkerImportTemplateController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        return Excel::download(
            new ProkerImportTemplateExport,
            'template-import-proker.xlsx'
        );
    }
}
