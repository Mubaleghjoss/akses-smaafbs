<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AssessmentMasterTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssessmentMasterTemplateController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $user = auth()->user();

        abort_unless(config('assessment.enabled'), Response::HTTP_NOT_FOUND);
        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless(
            $user->hasFullAdminAccess()
                || (
                    $user->canViewModule('penilaian')
                    && $user->can('penilaian.period.manage')
                ),
            Response::HTTP_FORBIDDEN,
        );

        return Excel::download(
            new AssessmentMasterTemplateExport,
            'template-master-penilaian-asts-asas.xlsx',
            null,
            [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ],
        );
    }
}
