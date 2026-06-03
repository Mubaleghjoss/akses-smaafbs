<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdminUserCredentialExport;
use App\Http\Controllers\Controller;
use App\Support\Admin\AdminUserCredentialShareSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminUserCredentialDocumentController extends Controller
{
    public function print(string $token): View
    {
        $payload = $this->resolvePayload($token);

        return view('admin.user-credentials.print', [
            'credentials' => $payload['credentials'],
            'generatedAt' => AdminUserCredentialShareSupport::generatedAtLabel($payload['generated_at'] ?? null),
            'generatedBy' => $payload['generated_by'] ?? '-',
        ]);
    }

    public function export(string $token): BinaryFileResponse
    {
        $payload = $this->resolvePayload($token);

        return Excel::download(
            new AdminUserCredentialExport(
                $payload['credentials'],
                AdminUserCredentialShareSupport::generatedAtLabel($payload['generated_at'] ?? null),
                $payload['generated_by'] ?? '-',
            ),
            'daftar-kredensial-reset-password.xlsx'
        );
    }

    protected function resolvePayload(string $token): array
    {
        abort_unless(auth()->check() && auth()->user()?->hasFullAdminAccess(), Response::HTTP_FORBIDDEN);

        $payload = AdminUserCredentialShareSupport::payload($token);

        abort_if(! is_array($payload) || ! isset($payload['credentials']), Response::HTTP_NOT_FOUND);

        return $payload;
    }
}
