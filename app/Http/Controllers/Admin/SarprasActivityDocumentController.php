<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SarprasActivityExport;
use App\Http\Controllers\Admin\Concerns\AuthorizesSarprasDocuments;
use App\Http\Controllers\Controller;
use App\Models\SarprasActivity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SarprasActivityDocumentController extends Controller
{
    use AuthorizesSarprasDocuments;

    public function print(): View
    {
        $this->authorizeSarprasModule('sarpras_activity');

        $records = SarprasActivity::query()
            ->orderByDesc('tanggal_pengerjaan')
            ->orderByDesc('id')
            ->get()
            ->each(function (SarprasActivity $record): void {
                $record->setAttribute('foto_sebelum_print_src', $this->printableAssetSource($record->fotoSebelumUrl()));
                $record->setAttribute('foto_sesudah_print_src', $this->printableAssetSource($record->fotoSesudahUrl()));
            });

        return view('admin.sarpras.activity-print', [
            'records' => $records,
            'schoolName' => $this->sarprasSchoolName(),
            'letterhead' => $this->sarprasLetterhead(),
            'printMode' => true,
            'pdfMode' => false,
        ]);
    }

    public function export(): BinaryFileResponse
    {
        $this->authorizeSarprasModule('sarpras_activity');

        return Excel::download(
            new SarprasActivityExport,
            'kegiatan-sarpras.xlsx'
        );
    }

    public function pdf(): Response
    {
        $this->authorizeSarprasModule('sarpras_activity');

        $records = SarprasActivity::query()
            ->orderByDesc('tanggal_pengerjaan')
            ->orderByDesc('id')
            ->get()
            ->each(function (SarprasActivity $record): void {
                $record->setAttribute('foto_sebelum_print_src', $this->printableAssetSource($record->fotoSebelumUrl()));
                $record->setAttribute('foto_sesudah_print_src', $this->printableAssetSource($record->fotoSesudahUrl()));
            });

        return $this->renderSarprasPdf(
            'admin.sarpras.activity-print',
            [
                'records' => $records,
                'schoolName' => $this->sarprasSchoolName(),
                'letterhead' => $this->sarprasLetterhead(),
            ],
            'kegiatan-sarpras.pdf'
        );
    }
}
