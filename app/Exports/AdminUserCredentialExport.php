<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class AdminUserCredentialExport implements FromArray, ShouldAutoSize, WithEvents
{
    /**
     * @param  array<int, array{name:string, username:string, password:string, created?:bool}>  $credentials
     */
    public function __construct(
        protected array $credentials,
        protected ?string $generatedAt = null,
        protected ?string $generatedBy = null,
    ) {}

    public function array(): array
    {
        $rows = [
            ['DAFTAR KREDENSIAL RESET PASSWORD'],
            ['Dibuat Pada', $this->generatedAt ?: '-'],
            ['Dibuat Oleh', $this->generatedBy ?: '-'],
            [],
            ['No', 'Nama', 'Username', 'Password Baru', 'Status'],
        ];

        foreach (array_values($this->credentials) as $index => $credential) {
            $rows[] = [
                $index + 1,
                $credential['name'] ?? '-',
                $credential['username'] ?? '-',
                $credential['password'] ?? '-',
                'Reset default',
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(count($this->credentials) + 5, 5);

                $sheet->mergeCells('A1:E1');
                $sheet->freezePane('A5');

                $sheet->getStyle('A1:E1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => 'center'],
                ]);

                $sheet->getStyle('A5:E5')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => 'solid',
                        'color' => ['rgb' => 'FFF176'],
                    ],
                ]);

                $sheet->getStyle("A5:E{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => 'thin',
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);
            },
        ];
    }
}
