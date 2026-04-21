<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ProkerPeriodExport;
use App\Http\Controllers\Controller;
use App\Models\Proker;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProkerExportController extends Controller
{
    public function __invoke(int $periode_tahun): BinaryFileResponse
    {
        abort_unless(
            Proker::query()->where('periode_tahun', $periode_tahun)->exists(),
            404,
        );

        return Excel::download(
            new ProkerPeriodExport($periode_tahun),
            "proker-periode-{$periode_tahun}.xlsx"
        );
    }
}
