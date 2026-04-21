<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SarprasRoomInventoryExport;
use App\Exports\SarprasRoomInventoriesWorkbookExport;
use App\Http\Controllers\Admin\Concerns\AuthorizesSarprasDocuments;
use App\Http\Controllers\Controller;
use App\Models\SarprasRoomInventory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SarprasRoomInventoryDocumentController extends Controller
{
    use AuthorizesSarprasDocuments;

    public function print(SarprasRoomInventory $sarprasRoomInventory): View
    {
        $this->authorizeSarprasModule('sarpras_room_inventory');

        $record = SarprasRoomInventory::query()
            ->with('items')
            ->whereKey($sarprasRoomInventory->getKey())
            ->firstOrFail();

        return view('admin.sarpras.room-inventory-print', [
            'record' => $record,
            'schoolName' => $this->sarprasSchoolName(),
            'letterhead' => $this->sarprasLetterhead(),
            'printMode' => true,
            'pdfMode' => false,
        ]);
    }

    public function export(SarprasRoomInventory $sarprasRoomInventory): BinaryFileResponse
    {
        $this->authorizeSarprasModule('sarpras_room_inventory');

        $record = SarprasRoomInventory::query()
            ->with('items')
            ->whereKey($sarprasRoomInventory->getKey())
            ->firstOrFail();

        return Excel::download(
            new SarprasRoomInventoryExport($record),
            'inventaris-ruangan-'.$record->getKey().'.xlsx'
        );
    }

    public function exportAll(): BinaryFileResponse
    {
        $this->authorizeSarprasModule('sarpras_room_inventory');

        $records = SarprasRoomInventory::query()
            ->with('items')
            ->orderBy('nama_gedung')
            ->orderBy('nama_ruang')
            ->get();

        return Excel::download(
            new SarprasRoomInventoriesWorkbookExport($records),
            'inventaris-ruangan-semua.xlsx'
        );
    }

    public function pdf(SarprasRoomInventory $sarprasRoomInventory): Response
    {
        $this->authorizeSarprasModule('sarpras_room_inventory');

        $record = SarprasRoomInventory::query()
            ->with('items')
            ->whereKey($sarprasRoomInventory->getKey())
            ->firstOrFail();

        return $this->renderSarprasPdf(
            'admin.sarpras.room-inventory-print',
            [
                'record' => $record,
                'schoolName' => $this->sarprasSchoolName(),
                'letterhead' => $this->sarprasLetterhead(),
            ],
            'inventaris-ruangan-'.$record->getKey().'.pdf'
        );
    }
}
