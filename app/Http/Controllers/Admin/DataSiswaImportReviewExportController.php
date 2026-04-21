<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DataSiswaImportReviewExport;
use App\Http\Controllers\Controller;
use App\Support\DataSiswa\DataSiswaImportReviewShareSupport;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataSiswaImportReviewExportController extends Controller
{
    public function __invoke(string $token): BinaryFileResponse
    {
        $payload = DataSiswaImportReviewShareSupport::payload($token);

        abort_if(! is_array($payload) || ! isset($payload['rows']), Response::HTTP_NOT_FOUND);

        return Excel::download(
            new DataSiswaImportReviewExport($payload['rows']),
            'laporan-review-import-data-tes-siswa.xlsx'
        );
    }
}
