<?php

namespace App\Http\Controllers\Admin;

use App\Exports\BoardingBacaanAssessmentsExport;
use App\Filament\Resources\BoardingPencapaianResource;
use App\Http\Controllers\Controller;
use App\Models\BoardingPencapaian;
use App\Models\DataSiswa;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BoardingBacaanAssessmentExportController extends Controller
{
    public function __invoke(BoardingPencapaian $boardingPencapaian): BinaryFileResponse
    {
        $user = auth()->user();

        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless(
            $user->hasFullAdminAccess() || $user->canViewModule('boarding_pencapaian'),
            Response::HTTP_FORBIDDEN,
        );

        $pencapaian = BoardingPencapaianResource::getEloquentQuery()
            ->whereHas('siswa', fn ($query) => DataSiswa::applyVisibleScope($query, $user))
            ->with('siswa:id,nama,rombel_saat_ini')
            ->whereKey($boardingPencapaian->getKey())
            ->firstOrFail();

        $filename = 'tabel-bacaan-'.Str::slug((string) ($pencapaian->siswa?->nama ?: 'murid')).'.xlsx';

        return Excel::download(new BoardingBacaanAssessmentsExport($pencapaian), $filename);
    }
}
