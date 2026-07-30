<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AssessmentArraySheetExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $hiddenColumns
     */
    public function __construct(
        protected array $rows,
        protected string $sheetTitle,
        protected array $hiddenColumns = [],
        protected bool $freezeHeader = true,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                if ($this->freezeHeader) {
                    $sheet->freezePane('A2');
                }

                $lastColumn = $sheet->getHighestDataColumn();
                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0F766E'],
                    ],
                ]);
                $sheet->setAutoFilter("A1:{$lastColumn}1");

                foreach ($this->hiddenColumns as $column) {
                    $sheet->getColumnDimension($column)->setVisible(false);
                }
            },
        ];
    }
}
