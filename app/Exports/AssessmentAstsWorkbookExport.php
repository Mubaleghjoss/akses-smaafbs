<?php

namespace App\Exports;

use App\Exports\Sheets\AssessmentArraySheetExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AssessmentAstsWorkbookExport implements WithMultipleSheets
{
    /** @param array<string, array<int, array<int, mixed>>> $sheets */
    public function __construct(protected array $sheets) {}

    public function sheets(): array
    {
        return collect($this->sheets)
            ->map(fn (array $rows, string $title): AssessmentArraySheetExport => new AssessmentArraySheetExport($rows, $title))
            ->values()
            ->all();
    }
}
