<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentSyncPreviewRequest;
use App\Support\StudentSync\StudentSyncPreviewService;
use Illuminate\Http\JsonResponse;

class StudentSyncPreviewController extends Controller
{
    public function __invoke(
        StudentSyncPreviewRequest $request,
        StudentSyncPreviewService $service,
    ): JsonResponse {
        $clientId = (string) $request->attributes->get('student_sync_client_id');
        $actorId = $request->user()?->getAuthIdentifier();

        return response()->json($service->preview(
            $clientId,
            $request->validated('students'),
            is_numeric($actorId) ? (int) $actorId : null,
        ));
    }
}
