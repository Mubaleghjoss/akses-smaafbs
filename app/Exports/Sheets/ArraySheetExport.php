<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class ArraySheetExport implements FromArray, ShouldAutoSize, WithTitle
{
    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function __construct(
        protected array $rows,
        protected string $title,
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->title;
    }
}
