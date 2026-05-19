<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BerkasGuru;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BerkasGuruDocumentController extends Controller
{
    public function preview(BerkasGuru $berkasGuru): View
    {
        $record = $this->resolveAuthorizedRecord($berkasGuru);

        return view('admin.guru.berkas-preview', [
            'berkasGuru' => $record,
        ]);
    }

    public function download(BerkasGuru $berkasGuru): StreamedResponse
    {
        $record = $this->resolveAuthorizedRecord($berkasGuru);
        $path = $record->resolvedFilePath();

        abort_unless($path !== null && Storage::disk('public')->exists($path), Response::HTTP_NOT_FOUND);

        return Storage::disk('public')->download($path, $record->displayFileName());
    }

    protected function resolveAuthorizedRecord(BerkasGuru $berkasGuru): BerkasGuru
    {
        $user = auth()->user();

        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless($user->hasRole('admin') || $user->canViewModule('berkas_guru'), Response::HTTP_FORBIDDEN);

        return BerkasGuru::query()
            ->visibleToUser($user)
            ->with(['guru', 'jenisBerkas', 'tugasTambahanHistory'])
            ->whereKey($berkasGuru->getKey())
            ->firstOrFail();
    }
}
