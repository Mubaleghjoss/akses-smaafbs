<?php

namespace App\Http\Controllers\Admin;

use App\Exports\BoardingRapotExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\ResolvesSchoolLetterhead;
use App\Models\BoardingRapot;
use App\Models\DataSiswa;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BoardingRapotDocumentController extends Controller
{
    use ResolvesSchoolLetterhead;

    public function preview(BoardingRapot $boardingRapot): View
    {
        $rapot = $this->resolveRecord($boardingRapot);
        $payload = $rapot->rekap_payload ?: $rapot->buildRekapPayload();

        return view('admin.boarding.rapot-preview', [
            'rapot' => $rapot,
            'payload' => $payload,
            'letterhead' => $this->schoolLetterhead(),
            'generatedAt' => now(),
            'printMode' => false,
        ]);
    }

    public function print(BoardingRapot $boardingRapot): View
    {
        $rapot = $this->resolveRecord($boardingRapot);
        $payload = $rapot->rekap_payload ?: $rapot->buildRekapPayload();

        return view('admin.boarding.rapot-preview', [
            'rapot' => $rapot,
            'payload' => $payload,
            'letterhead' => $this->schoolLetterhead(),
            'generatedAt' => now(),
            'printMode' => true,
        ]);
    }

    public function export(BoardingRapot $boardingRapot): BinaryFileResponse
    {
        $rapot = $this->resolveRecord($boardingRapot);
        $payload = $rapot->rekap_payload ?: $rapot->buildRekapPayload();
        $filename = 'rapot-boarding-'.Str::slug((string) ($rapot->siswa?->nama ?: 'murid')).'-'.Str::slug((string) $rapot->periode_tahun).'-'.$rapot->semester.'.xlsx';

        return Excel::download(new BoardingRapotExport($rapot, $payload), $filename);
    }

    protected function resolveRecord(BoardingRapot $boardingRapot): BoardingRapot
    {
        $user = auth()->user();

        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless(
            $user->hasRole('admin') || $user->canViewModule('boarding_rapot'),
            Response::HTTP_FORBIDDEN,
        );

        $record = BoardingRapot::query()
            ->select([
                'id',
                'siswa_id',
                'pamong_user_id',
                'periode_tahun',
                'semester',
                'nomor_dokumen',
                'predikat_boarding',
                'status_rapot',
                'tanggal_rapot',
                'generated_at',
                'rekap_payload',
                'ringkasan_pencapaian',
                'catatan_pamong',
                'rekomendasi_tindak_lanjut',
                'wali_pamong_nama',
                'kepala_boarding_nama',
                'mudir_asrama_nama',
                'tempat_cetak',
            ])
            ->forDocument($user)
            ->whereHas('siswa', fn ($query) => DataSiswa::applyVisibleScope($query, $user))
            ->whereKey($boardingRapot->getKey())
            ->firstOrFail();

        $record->syncFromSources();

        $record->refresh();
        $record->loadMissing([
            'siswa:id,nama,rombel_saat_ini,jk,status',
            'pamongUser:id,name',
        ]);

        return $record;
    }
}
