<?php

namespace App\Exports;

use App\Models\SarprasMonthlyAgenda;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SarprasMonthlyAgendaExport implements FromArray, ShouldAutoSize, WithEvents
{
    public function array(): array
    {
        $rows = [
            ['AGENDA BULANAN'],
            [''],
            ['No', 'Jenis Kegiatan', 'Tindak Lanjut', '', 'PJ', 'Catatan', 'Bulan Agenda'],
            ['', '', 'Sudah', 'Belum', '', '', ''],
        ];

        $records = SarprasMonthlyAgenda::query()
            ->orderByDesc('bulan_agenda')
            ->orderBy('urutan')
            ->get();

        foreach ($records as $record) {
            $rows[] = [
                $record->urutan,
                $record->jenis_kegiatan,
                $record->tindak_lanjut_status === SarprasMonthlyAgenda::STATUS_SUDAH ? 'v' : '',
                $record->tindak_lanjut_status === SarprasMonthlyAgenda::STATUS_BELUM ? 'v' : '',
                $record->penanggung_jawab,
                $record->catatan,
                $record->bulan_agenda?->translatedFormat('F Y'),
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

                $sheet->mergeCells('A1:G1');
                $sheet->mergeCells('A2:G2');
                $sheet->mergeCells('A3:A4');
                $sheet->mergeCells('B3:B4');
                $sheet->mergeCells('C3:D3');
                $sheet->mergeCells('E3:E4');
                $sheet->mergeCells('F3:F4');
                $sheet->mergeCells('G3:G4');
                $sheet->freezePane('A5');

                $sheet->getStyle('A1:G1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A3:G4')->applyFromArray([
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

                $sheet->getStyle("A5:G{$lastRow}")->applyFromArray([
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
