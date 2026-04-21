<?php

namespace App\Exports;

use App\Filament\Resources\SarprasBospInventoryResource;
use App\Models\SarprasBospInventory;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SarprasBospInventoryExport implements FromArray, ShouldAutoSize, WithEvents
{
    public function array(): array
    {
        $rows = [
            ['DAFTAR BOSP'],
            [''],
            ['No Urut', 'Nama Barang', 'Quality', 'Bulan Beli', 'Tahun Beli', 'Kode Barang', 'Lokasi Barang', 'Tanggal Datang', 'Total Harga', 'Catatan'],
        ];

        $records = SarprasBospInventory::query()
            ->orderByDesc('tanggal_datang')
            ->orderBy('nomor_urut')
            ->get();

        foreach ($records as $record) {
            $rows[] = [
                $record->nomor_urut,
                $record->nama_barang,
                $record->quality,
                SarprasBospInventoryResource::bulanOptions()[$record->bulan_beli] ?? '-',
                $record->tahun_beli,
                $record->kode_barang,
                $record->lokasi_barang,
                $record->tanggal_datang?->format('d F Y'),
                (float) ($record->total_harga ?? 0),
                $record->catatan,
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->mergeCells('A1:J1');
                $sheet->mergeCells('A2:J2');
                $sheet->freezePane('A4');

                $sheet->getStyle('A1:J1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A3:J3')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFF200'],
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

                $sheet->getStyle("A4:J{$lastRow}")->applyFromArray([
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

                $sheet->getStyle("I4:I{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("A3:J{$lastRow}")->getAlignment()->setWrapText(true);
            },
        ];
    }
}
