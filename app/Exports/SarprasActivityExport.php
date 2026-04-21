<?php

namespace App\Exports;

use App\Models\SarprasActivity;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SarprasActivityExport implements FromArray, ShouldAutoSize, WithEvents
{
    public function array(): array
    {
        $rows = [
            ['KEGIATAN SARPRAS'],
            [''],
            ['No', 'Tanggal Pengerjaan', 'Perbaikan', 'PJ', 'Hasil Akhir', 'Foto Kegiatan', '', 'Pelaksana (Paraf)', 'Catatan'],
            ['', '', '', '', '', 'Sebelum', 'Sesudah', '', ''],
        ];

        $records = SarprasActivity::query()
            ->orderByDesc('tanggal_pengerjaan')
            ->orderByDesc('id')
            ->get();

        foreach ($records as $index => $record) {
            $rows[] = [
                $index + 1,
                $record->tanggal_pengerjaan?->translatedFormat('d F Y'),
                $record->perbaikan,
                $record->penanggung_jawab,
                $record->hasil_akhir,
                $record->fotoSebelumUrl(),
                $record->fotoSesudahUrl(),
                $record->pelaksana_paraf,
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

                $sheet->mergeCells('A1:I1');
                $sheet->mergeCells('A2:I2');
                $sheet->mergeCells('A3:A4');
                $sheet->mergeCells('B3:B4');
                $sheet->mergeCells('C3:C4');
                $sheet->mergeCells('D3:D4');
                $sheet->mergeCells('E3:E4');
                $sheet->mergeCells('F3:G3');
                $sheet->mergeCells('H3:H4');
                $sheet->mergeCells('I3:I4');
                $sheet->freezePane('A5');

                $sheet->getStyle('A1:I1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A3:I4')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFF200'],
                    ],
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
                ]);

                $sheet->getStyle("A5:I{$lastRow}")->applyFromArray([
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
            },
        ];
    }
}
