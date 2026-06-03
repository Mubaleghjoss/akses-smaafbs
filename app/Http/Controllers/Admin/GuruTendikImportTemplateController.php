<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GuruTendikImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GuruTendikImportTemplateController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $user = auth()->user();

        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless($user->hasFullAdminAccess() || $user->canViewModule('guru_tendik'), Response::HTTP_FORBIDDEN);

        return Excel::download(new GuruTendikImportTemplateExport, 'template-import-guru-tendik-niy.xlsx', null, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
