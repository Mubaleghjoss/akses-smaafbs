<?php

namespace App\Exports;

use App\Models\BoardingRapot;
use App\Models\BoardingPencapaian;
use App\Support\Boarding\BoardingRapotSheetRows;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BoardingRapotExport implements WithMultipleSheets
{
    public function __construct(
        protected BoardingRapot $rapot,
        protected ?array $payload = null,
    ) {}

    public function sheets(): array
    {
        $payload = $this->payload ?? $this->rapot->rekap_payload ?: $this->rapot->buildRekapPayload();
        $materiScope = BoardingPencapaian::normalizeMateriRapotScope($payload['pencapaian']['materi_rapot_scope'] ?? null);

        return $materiScope === BoardingPencapaian::MATERI_RAPOT_SCOPE_MT
            ? [new BoardingRapotMtSheet($payload)]
            : [new BoardingRapotMateriBoardingSheet($payload)];
    }
}

abstract class BoardingRapotSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(
        protected array $payload,
    ) {}

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $this->styleSheet($sheet);
            },
        ];
    }

    protected function baseTableStyle(): array
    {
        return [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];
    }

    protected function headerStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0097A7'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
    }

    abstract protected function styleSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void;
}

class BoardingRapotMateriBoardingSheet extends BoardingRapotSheet
{
    public function title(): string
    {
        return 'Halaman 1';
    }

    public function array(): array
    {
        return BoardingRapotSheetRows::materiBoardingRows($this->payload);
    }

    protected function styleSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $sheet->mergeCells('A1:B1');
        $sheet->mergeCells('A2:A3');
        $sheet->mergeCells('A5:B5');
        $sheet->mergeCells('A6:A9');
        $sheet->mergeCells('A10:B10');
        $sheet->mergeCells('A11:B11');
        $sheet->mergeCells('A12:B12');
        $sheet->mergeCells('A13:B13');

        $sheet->getStyle('A1:C13')->applyFromArray($this->baseTableStyle());
        $sheet->getStyle('A1:C1')->applyFromArray($this->headerStyle());

        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(54);

        foreach (range(1, 13) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(22);
        }
    }
}

class BoardingRapotMtSheet extends BoardingRapotSheet
{
    public function title(): string
    {
        return 'Halaman 2';
    }

    public function array(): array
    {
        return BoardingRapotSheetRows::mtRows($this->payload);
    }

    protected function styleSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $sheet->getStyle('A1:B12')->applyFromArray($this->baseTableStyle());
        $sheet->getStyle('A1:B1')->applyFromArray($this->headerStyle());

        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(62);

        foreach (range(1, 12) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(22);
        }
    }
}
