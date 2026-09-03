<?php

namespace App\Http\Controllers\Admin;

use App\Exports\BelajarIdImportTemplateExport;
use App\Filament\Resources\BelajarIdAccountResource;
use App\Filament\Resources\BelajarIdGuruResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BelajarIdImportTemplateController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $user = auth()->user();

        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless(
            $user->hasFullAdminAccess()
            || BelajarIdAccountResource::canViewAny()
            || BelajarIdGuruResource::canViewAny(),
            Response::HTTP_FORBIDDEN
        );

        return Excel::download(new BelajarIdImportTemplateExport, 'template-belajar-id.xlsx', null, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
