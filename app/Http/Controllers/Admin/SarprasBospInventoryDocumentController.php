<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SarprasBospInventoryExport;
use App\Http\Controllers\Admin\Concerns\AuthorizesSarprasDocuments;
use App\Http\Controllers\Controller;
use App\Models\SarprasBospInventory;
use App\Support\Sarpras\SarprasBospStickerImage;
use App\Support\Sarpras\SarprasStickerSettings;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

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

    public function sticker(Request $request, SarprasBospInventory $sarprasBospInventory): Response
    {
        $this->authorizeSarprasModule('sarpras_bosp_inventory');

        if ($this->stickerFormat($request) === 'png') {
            return $this->renderStickerPngDownload($sarprasBospInventory);
        }

        return $this->renderStickerPdf(
            collect([$sarprasBospInventory]),
            $this->stickerDownloadFilename(collect([$sarprasBospInventory]), 'pdf'),
            true
        );
    }

    public function stickers(Request $request): Response
    {
        $this->authorizeSarprasModule('sarpras_bosp_inventory');

        $ids = $this->selectedStickerIds($request);

        if ($ids->isNotEmpty()) {
            $records = SarprasBospInventory::query()
                ->whereKey($ids->all())
                ->get()
                ->sortBy(fn (SarprasBospInventory $record): int => $ids->search($record->getKey()))
                ->values();
        } else {
            $records = SarprasBospInventory::query()
                ->orderByDesc('tanggal_datang')
                ->orderBy('nomor_urut')
                ->get();
        }

        abort_if($records->isEmpty(), Response::HTTP_NOT_FOUND);

        if ($this->stickerFormat($request) === 'png') {
            return $this->renderStickerPngFiles($records);
        }

        return $this->renderStickerPdf(
            $records,
            $this->stickerDownloadFilename($records, 'pdf'),
            false
        );
    }

    /**
     * @param  Collection<int, SarprasBospInventory>  $records
     */
    private function renderStickerPdf(Collection $records, string $filename, bool $single): Response
    {
        $qr = new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'outputBase64' => true,
            'quietzoneSize' => 2,
            'scale' => 5,
        ]));
        $letterhead = $this->sarprasLetterhead();
        $settings = SarprasStickerSettings::all();
        $logoSrc = SarprasStickerSettings::imageDataUri(
            SarprasStickerSettings::resolvedLogoPath($letterhead['logo_src'])
        );

        $pdf = app('dompdf.wrapper');
        $pdf->setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ]);

        $viewData = [
            'records' => $records,
            'schoolName' => $letterhead['site_name'] ?: $this->sarprasSchoolName(),
            'logoSrc' => $logoSrc,
            'single' => $single,
            'stickerSettings' => $settings,
            'qrImageFor' => fn (SarprasBospInventory $record): string => $qr->render(
                route('sarpras.bosp-inventories.show', $record),
            ),
            'stickerImageFor' => fn (SarprasBospInventory $record): string => SarprasBospStickerImage::renderDataUri(
                $record,
                $settings,
                $logoSrc,
                $qr->render(route('sarpras.bosp-inventories.show', $record)),
            ),
        ];

        $paper = $single
            ? [
                0,
                0,
                $this->mmToPoints((float) $settings[SarprasStickerSettings::WIDTH_MM]),
                $this->mmToPoints((float) $settings[SarprasStickerSettings::HEIGHT_MM]),
            ]
            : 'a4';

        return $pdf
            ->loadView('admin.sarpras.bosp-stickers', $viewData)
            ->setPaper($paper, 'portrait')
            ->download($filename);
    }

    private function renderStickerPngDownload(SarprasBospInventory $record): Response
    {
        return response($this->renderStickerPng($record), Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$this->stickerRecordFilename($record, 'png').'"',
        ]);
    }

    /**
     * @param  Collection<int, SarprasBospInventory>  $records
     */
    private function renderStickerPngFiles(Collection $records): Response
    {
        if ($records->count() === 1) {
            return $this->renderStickerPngDownload($records->first());
        }

        abort_unless(class_exists(ZipArchive::class), Response::HTTP_INTERNAL_SERVER_ERROR, 'Ekstensi ZIP PHP belum aktif.');

        $zipPath = tempnam(storage_path('app'), 'stiker-bosp-');
        abort_if($zipPath === false, Response::HTTP_INTERNAL_SERVER_ERROR, 'Tidak bisa membuat file ZIP sementara.');

        $zip = new ZipArchive();
        abort_if($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true, Response::HTTP_INTERNAL_SERVER_ERROR, 'Tidak bisa membuat file ZIP stiker.');

        $usedNames = [];
        foreach ($records as $record) {
            $filename = $this->uniqueStickerRecordFilename($record, $usedNames);
            $zip->addFromString($filename, $this->renderStickerPng($record));
        }

        $zip->close();

        return response()
            ->download($zipPath, $this->stickerDownloadFilename($records, 'zip'), [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }

    private function renderStickerPng(SarprasBospInventory $record): string
    {
        $qr = new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'outputBase64' => true,
            'quietzoneSize' => 2,
            'scale' => 5,
        ]));
        $letterhead = $this->sarprasLetterhead();
        $settings = SarprasStickerSettings::all();
        $logoSrc = SarprasStickerSettings::imageDataUri(
            SarprasStickerSettings::resolvedLogoPath($letterhead['logo_src'])
        );
        $dataUri = SarprasBospStickerImage::renderDataUri(
            $record,
            $settings,
            $logoSrc,
            $qr->render(route('sarpras.bosp-inventories.show', $record)),
        );

        $contents = base64_decode(Str::after($dataUri, ','), true);
        abort_if($contents === false, Response::HTTP_INTERNAL_SERVER_ERROR, 'Tidak bisa membuat PNG stiker.');

        return $contents;
    }

    private function stickerFormat(Request $request): string
    {
        return strtolower((string) $request->query('format')) === 'png' ? 'png' : 'pdf';
    }

    /**
     * @return Collection<int, int>
     */
    private function selectedStickerIds(Request $request): Collection
    {
        $rawIds = $request->query('selected', $request->query('ids', []));
        $values = is_array($rawIds)
            ? collect($rawIds)->flatten()
            : collect(preg_split('/[\s,]+/', (string) $rawIds) ?: []);

        return $values
            ->map(fn ($id): string => trim((string) $id))
            ->filter(fn (string $id): bool => $id !== '' && ctype_digit($id))
            ->map(fn (string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, SarprasBospInventory>  $records
     */
    private function stickerDownloadFilename(Collection $records, string $extension): string
    {
        $first = $records->first();

        if ($first instanceof SarprasBospInventory && $records->count() === 1) {
            return $this->stickerRecordFilename($first, $extension);
        }

        $base = $first instanceof SarprasBospInventory
            ? $this->stickerRecordBaseName($first)
            : 'inventaris';

        return $base.'-dan-'.max(0, $records->count() - 1).'-barang.'.$extension;
    }

    private function stickerRecordFilename(SarprasBospInventory $record, string $extension): string
    {
        return $this->stickerRecordBaseName($record).'.'.$extension;
    }

    private function stickerRecordBaseName(SarprasBospInventory $record): string
    {
        $code = $this->filenameSlug((string) $record->kode_barang);

        return 'stiker-'.$this->filenameSlug((string) $record->nama_barang).'-'.($code !== 'barang' ? $code : $record->getKey());
    }

    /**
     * @param  array<string, bool>  $usedNames
     */
    private function uniqueStickerRecordFilename(SarprasBospInventory $record, array &$usedNames): string
    {
        $filename = $this->stickerRecordFilename($record, 'png');

        if (! isset($usedNames[$filename])) {
            $usedNames[$filename] = true;

            return $filename;
        }

        $base = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $counter = 2;

        do {
            $fallback = $base.'-'.$counter.'.'.$extension;
            $counter++;
        } while (isset($usedNames[$fallback]));

        $usedNames[$fallback] = true;

        return $fallback;
    }

    private function filenameSlug(string $value): string
    {
        $slug = Str::slug(Str::limit($value, 80, ''));

        return $slug !== '' ? $slug : 'barang';
    }

    private function mmToPoints(float $value): float
    {
        return $value * 72 / 25.4;
    }
}
