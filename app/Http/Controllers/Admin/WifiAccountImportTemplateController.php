<?php

namespace App\Http\Controllers\Admin;

use App\Exports\WifiAccountImportTemplateExport;
use App\Filament\Resources\WifiAccountResource;
use App\Filament\Resources\WifiGuruResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WifiAccountImportTemplateController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $user = auth()->user();

        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless(
            $user->hasFullAdminAccess()
            || WifiAccountResource::canViewAny()
            || WifiGuruResource::canViewAny(),
            Response::HTTP_FORBIDDEN
        );

        return Excel::download(new WifiAccountImportTemplateExport, 'template-akun-wifi.xlsx', null, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
