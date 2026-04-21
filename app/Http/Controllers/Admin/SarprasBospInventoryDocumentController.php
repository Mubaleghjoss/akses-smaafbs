<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SarprasBospInventoryExport;
use App\Http\Controllers\Admin\Concerns\AuthorizesSarprasDocuments;
use App\Http\Controllers\Controller;
use App\Models\SarprasBospInventory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SarprasBospInventoryDocumentController extends Controller
{
    use AuthorizesSarprasDocuments;

    public function print(): View
    {
        $this->authorizeSarprasModule('sarpras_bosp_inventory');

        $records = SarprasBospInventory::query()
            ->orderByDesc('tanggal_datang')
            ->orderBy('nomor_urut')
            ->get();

        return view('admin.sarpras.bosp-print', [
            'records' => $records,
            'schoolName' => $this->sarprasSchoolName(),
            'letterhead' => $this->sarprasLetterhead(),
            'printMode' => true,
            'pdfMode' => false,
        ]);
    }

    public function export(): BinaryFileResponse
    {
        $this->authorizeSarprasModule('sarpras_bosp_inventory');

        return Excel::download(
            new SarprasBospInventoryExport,
            'daftar-inventaris-bosp.xlsx'
        );
    }

    public function pdf(): Response
    {
        $this->authorizeSarprasModule('sarpras_bosp_inventory');

        $records = SarprasBospInventory::query()
            ->orderByDesc('tanggal_datang')
            ->orderBy('nomor_urut')
            ->get();

        return $this->renderSarprasPdf(
            'admin.sarpras.bosp-print',
            [
                'records' => $records,
                'schoolName' => $this->sarprasSchoolName(),
                'letterhead' => $this->sarprasLetterhead(),
            ],
            'daftar-inventaris-bosp.pdf'
        );
    }
}
