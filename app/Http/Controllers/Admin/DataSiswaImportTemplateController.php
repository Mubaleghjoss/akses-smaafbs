<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DataSiswaImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataSiswaImportTemplateController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $user = auth()->user();
        abort_unless($user instanceof User && ($user->hasFullAdminAccess() || $user->canViewModule('data_siswa')), Response::HTTP_FORBIDDEN);

        return Excel::download(
            new DataSiswaImportTemplateExport($user),
            'template-data-siswa-saat-ini.xlsx',
            ExcelFormat::XLSX,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }
}
