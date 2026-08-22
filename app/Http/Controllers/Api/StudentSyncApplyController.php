<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentSyncApplyRequest;
use App\Support\StudentSync\StudentSyncApplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class StudentSyncApplyController extends Controller
{
    public function __invoke(
        StudentSyncApplyRequest $request,
        StudentSyncApplyService $service,
    ): JsonResponse {
        $clientId = (string) $request->attributes->get('student_sync_client_id');
        $actorId = $request->user()?->getAuthIdentifier();

        try {
            $result = $service->apply(
                $clientId,
                (string) $request->validated('preview_token'),
                strtolower((string) $request->validated('payload_checksum')),
                (string) $request->header('X-Student-Sync-Idempotency-Key'),
                is_numeric($actorId) ? (int) $actorId : null,
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json($result);
    }
}
