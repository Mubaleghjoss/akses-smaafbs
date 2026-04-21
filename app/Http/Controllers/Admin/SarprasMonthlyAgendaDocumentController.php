<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SarprasMonthlyAgendaExport;
use App\Http\Controllers\Admin\Concerns\AuthorizesSarprasDocuments;
use App\Http\Controllers\Controller;
use App\Models\SarprasMonthlyAgenda;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SarprasMonthlyAgendaDocumentController extends Controller
{
    use AuthorizesSarprasDocuments;

    public function print(): View
    {
        $this->authorizeSarprasModule('sarpras_monthly_agenda');

        $records = SarprasMonthlyAgenda::query()
            ->orderByDesc('bulan_agenda')
            ->orderBy('urutan')
            ->get();

        return view('admin.sarpras.monthly-agenda-print', [
            'records' => $records,
            'schoolName' => $this->sarprasSchoolName(),
            'letterhead' => $this->sarprasLetterhead(),
            'printMode' => true,
            'pdfMode' => false,
        ]);
    }

    public function export(): BinaryFileResponse
    {
        $this->authorizeSarprasModule('sarpras_monthly_agenda');

        return Excel::download(
            new SarprasMonthlyAgendaExport,
            'agenda-bulanan-sarpras.xlsx'
        );
    }

    public function pdf(): Response
    {
        $this->authorizeSarprasModule('sarpras_monthly_agenda');

        $records = SarprasMonthlyAgenda::query()
            ->orderByDesc('bulan_agenda')
            ->orderBy('urutan')
            ->get();

        return $this->renderSarprasPdf(
            'admin.sarpras.monthly-agenda-print',
            [
                'records' => $records,
                'schoolName' => $this->sarprasSchoolName(),
                'letterhead' => $this->sarprasLetterhead(),
            ],
            'agenda-bulanan-sarpras.pdf'
        );
    }
}
