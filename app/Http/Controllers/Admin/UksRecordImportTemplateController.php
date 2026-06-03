<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UksRecordImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UksRecordImportTemplateController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $user = auth()->user();

        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless($user->hasFullAdminAccess() || $user->canViewModule('uks_records'), Response::HTTP_FORBIDDEN);

        return Excel::download(new UksRecordImportTemplateExport, 'template-import-uks-records.xlsx');
    }
}
