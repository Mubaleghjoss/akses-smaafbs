<?php

namespace App\Exports;

use App\Models\SarprasRoomInventory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SarprasRoomInventoriesWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int, SarprasRoomInventory>  $records
     */
    public function __construct(
        protected Collection $records,
    ) {}

    public function sheets(): array
    {
        return $this->records
            ->map(fn (SarprasRoomInventory $record): SarprasRoomInventoryExport => new SarprasRoomInventoryExport($record))
            ->all();
    }
}
