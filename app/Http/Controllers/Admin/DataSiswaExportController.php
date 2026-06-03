<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DataSiswaExport;
use App\Http\Controllers\Controller;
use App\Models\DataSiswa;
use App\Models\User;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataSiswaExportController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $user = auth()->user();

        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless($user->hasFullAdminAccess() || $user->canViewModule('data_siswa'), Response::HTTP_FORBIDDEN);
        abort_unless(DataSiswa::applyVisibleScope(DataSiswa::query(), $user)->exists(), 404);

        return Excel::download(
            new DataSiswaExport($user),
            'data-siswa-sekolah.xlsx'
        );
    }
}
