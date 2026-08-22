<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UksRecordExport;
use App\Http\Controllers\Controller;
use App\Models\UksRecord;
use App\Models\User;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UksRecordExportController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $user = auth()->user();

        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless($user->hasFullAdminAccess() || $user->canViewModule('uks_records'), Response::HTTP_FORBIDDEN);
        abort_unless(UksRecord::query()->exists(), Response::HTTP_NOT_FOUND);

        return Excel::download(new UksRecordExport, 'data-uks-records.xlsx');
    }
}
