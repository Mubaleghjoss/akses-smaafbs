<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GuruTendikExport;
use App\Http\Controllers\Controller;
use App\Models\GuruTendik;
use App\Models\User;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GuruTendikExportController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $user = auth()->user();

        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless($user->hasRole('admin') || $user->canViewModule('guru_tendik'), Response::HTTP_FORBIDDEN);
        abort_unless(GuruTendik::query()->exists(), Response::HTTP_NOT_FOUND);

        return Excel::download(new GuruTendikExport, 'data-guru-tendik-niy.xlsx', null, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
