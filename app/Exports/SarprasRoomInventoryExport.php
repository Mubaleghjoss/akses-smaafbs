<?php

namespace App\Exports;

use App\Models\SarprasRoomInventory;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Str;

class SarprasRoomInventoryExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(
        protected SarprasRoomInventory $record,
    ) {}

    public function array(): array
    {
        $rows = [
            ['DAFTAR INVENTARIS RUANGAN'],
            [''],
            ['Nama Gedung', $this->record->nama_gedung],
            ['Nama Ruang', $this->record->nama_ruang],
            ['Nomor Ruang', $this->record->nomor_ruang],
            [],
            ['No', 'Tanggal', 'Nama Barang', 'Jumlah', 'Kondisi Barang', 'Ket'],
        ];

        foreach ($this->record->items as $item) {
            $rows[] = [
                $item->urutan,
                $item->tanggal?->format('l, F j, Y'),
                $item->nama_barang,
                $item->jumlah,
                $item->kondisi_barang,
                $item->keterangan,
            ];
        }

        $rows[] = [];
        $rows[] = ['Kepala Sekolah', '', '', '', 'Mengetahui', ''];
        $rows[] = [$this->record->penanggung_jawab, '', '', '', $this->record->diketahui_oleh, ''];

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();
                $headerRow = 7;

                $sheet->mergeCells('A1:F1');
                $sheet->mergeCells('A2:F2');
                $sheet->mergeCells('B3:F3');
                $sheet->mergeCells('B4:F4');
                $sheet->mergeCells('B5:F5');
                $sheet->freezePane('A8');

                $sheet->getStyle('A1:F1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A{$headerRow}:F{$headerRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '00B0F0'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                $sheet->getStyle('A3:F5')->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                $sheet->getStyle("A8:F".($lastRow - 3))->applyFromArray([
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_TOP,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                $sheet->getStyle("A".($lastRow - 1).":F{$lastRow}")->applyFromArray([
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_TOP,
                        'wrapText' => true,
                    ],
                ]);
            },
        ];
    }

    public function title(): string
    {
        $base = trim((string) ($this->record->nama_ruang ?: 'Ruangan'));

        return Str::limit($base !== '' ? $base : 'Ruangan', 31, '');
    }
}
