<?php

namespace App\Exports;

use App\Models\BoardingKeuanganSiswa;
use App\Models\BoardingRapot;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BoardingRapotExport implements FromArray, ShouldAutoSize, WithEvents
{
    public function __construct(
        protected BoardingRapot $rapot,
        protected ?array $payload = null,
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $payload = $this->payload ?? $this->rapot->rekap_payload ?: $this->rapot->buildRekapPayload();
        $keuangan = $payload['keuangan'] ?? [];
        $totalTitipan = (int) ($keuangan['total_titipan'] ?? $keuangan['titipan_masuk'] ?? 0);
        $totalPemberian = (int) ($keuangan['total_pemberian'] ?? $keuangan['pemberian_uang_saku'] ?? 0);
        $totalKas = (int) ($keuangan['total_kas'] ?? $keuangan['setoran_kas'] ?? 0);
        $saldoTersisa = (int) ($keuangan['saldo_tersisa'] ?? 0);

        $rows = [
            [strtoupper((string) ($payload['school']['nama'] ?? 'SMA AFBS'))],
            [strtoupper((string) ($payload['school']['boarding_label'] ?? 'BOARDING SCHOOL'))],
            [$payload['school']['alamat'] ?? '-'],
            [],
            ['RAPOT BOARDING'],
            ['Nomor Dokumen', $payload['rapot']['nomor_dokumen'] ?? '-'],
            ['Status Dokumen', $payload['rapot']['status_rapot'] ?? '-'],
            ['Nama Murid', $payload['siswa']['nama'] ?? '-'],
            ['Rombel', $payload['siswa']['rombel'] ?? '-'],
            ['Jenis Kelamin', $payload['siswa']['jk'] ?? '-'],
            ['Status Siswa', $payload['siswa']['status'] ?? '-'],
            ['Periode', $payload['rapot']['periode_tahun'] ?? '-'],
            ['Semester', $payload['rapot']['semester'] ?? '-'],
            ['Tanggal Rapot', $payload['rapot']['tanggal_rapot'] ?? '-'],
            ['Predikat Boarding', $payload['rapot']['predikat_boarding'] ?? '-'],
            [],
            ['RINGKASAN CAPAIAN UTAMA'],
            ['Status Pencapaian', $payload['pencapaian']['status'] ?? '-'],
            ['Target Surat', $payload['pencapaian']['target']['surat'] ?? 0],
            ['Realisasi Surat', $payload['pencapaian']['realisasi']['surat'] ?? 0],
            ['Target Doa', $payload['pencapaian']['target']['doa'] ?? 0],
            ['Realisasi Doa', $payload['pencapaian']['realisasi']['doa'] ?? 0],
            ['Target Hadits', $payload['pencapaian']['target']['hadits'] ?? 0],
            ['Realisasi Hadits', $payload['pencapaian']['realisasi']['hadits'] ?? 0],
            ['Quran Tuntas', $payload['pencapaian']['surat_quran_tuntas'] ?? '-'],
            ['Hadits Tuntas', $payload['pencapaian']['hadits_tuntas'] ?? '-'],
            ['Hafalan Surat', $payload['pencapaian']['hafalan_surat'] ?? '-'],
            ['Hafalan Doa', $payload['pencapaian']['hafalan_doa'] ?? '-'],
            ['Hafalan Lainnya', $payload['pencapaian']['hafalan_lainnya'] ?? '-'],
            ['Target Berikutnya', $payload['pencapaian']['target_berikutnya'] ?? '-'],
            ['Catatan Pembinaan', $payload['pencapaian']['catatan'] ?? '-'],
            [],
            ['DETAIL TARGET PER KATEGORI'],
        ];

        $detailGroups = $payload['pencapaian']['detail_kelompok'] ?? [];

        if ($detailGroups === []) {
            $rows[] = ['Belum ada detail target boarding yang tercatat.'];
        } else {
            foreach ($detailGroups as $group) {
                $rows[] = [$group['judul'] ?? '-'];
                $rows[] = ['Nama Target', 'Target', 'Capaian', 'Satuan', 'Status', 'Tanggal Tuntas', 'Keterangan'];

                foreach ($group['rows'] ?? [] as $detail) {
                    $rows[] = [
                        $detail['nama_target'] ?? '-',
                        $detail['target_nilai'] ?? 0,
                        $detail['capaian_nilai'] ?? 0,
                        $detail['satuan'] ?? '-',
                        $detail['status_detail'] ?? '-',
                        $detail['tuntas_at'] ?? '-',
                        $detail['detail'] ?? '-',
                    ];
                }

                $rows[] = [];
            }
        }

        $rows = [
            ...$rows,
            ['CATATAN RAPOT'],
            ['Ringkasan Pencapaian', $this->rapot->ringkasan_pencapaian ?: '-'],
            ['Catatan Pamong', $this->rapot->catatan_pamong ?: '-'],
            ['Rekomendasi Tindak Lanjut', $this->rapot->rekomendasi_tindak_lanjut ?: '-'],
            [],
            ['RINGKASAN KEUANGAN'],
            ['Pamong', $keuangan['pamong_nama'] ?? '-'],
            ['Kategori Asrama', $keuangan['kategori_asrama'] ?? '-'],
            ['Titipan Masuk', BoardingKeuanganSiswa::formatRupiah($totalTitipan)],
            ['Pemberian Uang Saku', BoardingKeuanganSiswa::formatRupiah($totalPemberian)],
            ['Setoran Kas (Qurban + Isrun)', BoardingKeuanganSiswa::formatRupiah($totalKas)],
            ['Sisa di Pamong', BoardingKeuanganSiswa::formatRupiah($saldoTersisa)],
            [],
            ['RIWAYAT KONSELING TERBARU'],
            ['Tanggal', 'Kategori', 'Prioritas', 'Status', 'Ringkasan', 'Tindak Lanjut', 'Konselor'],
        ];

        $konselingRows = $payload['konseling'] ?? [];

        if ($konselingRows === []) {
            $rows[] = ['-', '-', '-', '-', 'Belum ada data konseling.', '-', '-'];
        } else {
            foreach ($konselingRows as $row) {
                $rows[] = [
                    $row['tanggal'] ?? '-',
                    $row['kategori'] ?? '-',
                    $row['prioritas'] ?? '-',
                    $row['status_tindak_lanjut'] ?? '-',
                    $row['ringkasan_masalah'] ?? '-',
                    $row['tindak_lanjut'] ?? '-',
                    $row['konselor'] ?? '-',
                ];
            }
        }

        return [
            ...$rows,
            [],
            ['PENANDATANGAN'],
            ['Tempat Cetak', $payload['school']['kota'] ?? '-'],
            ['Wali Pamong', $payload['signatures']['wali_pamong_nama'] ?? '-'],
            ['Kepala Boarding', $payload['signatures']['kepala_boarding_nama'] ?? '-'],
            ['Mudir Asrama', $payload['signatures']['mudir_asrama_nama'] ?? '-'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->freezePane('A6');

                foreach (range(1, $lastRow) as $row) {
                    $rowValues = [];

                    foreach (range('A', 'G') as $column) {
                        $rowValues[$column] = trim((string) ($sheet->getCell($column.$row)->getValue() ?? ''));
                    }

                    $filledColumns = collect($rowValues)->filter()->keys()->values()->all();

                    if ($filledColumns === ['A']) {
                        $sheet->mergeCells("A{$row}:G{$row}");
                    }
                }

                $sheet->getStyle('A1:G3')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A1:G1')->getFont()->setSize(16);
                $sheet->getStyle('A2:G2')->getFont()->setSize(13);
                $sheet->getStyle('A5:G5')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                $sectionTitles = [
                    'RINGKASAN CAPAIAN UTAMA',
                    'DETAIL TARGET PER KATEGORI',
                    'CATATAN RAPOT',
                    'RINGKASAN KEUANGAN',
                    'RIWAYAT KONSELING TERBARU',
                    'PENANDATANGAN',
                ];

                foreach (range(1, $lastRow) as $row) {
                    $cellValue = trim((string) ($sheet->getCell("A{$row}")->getValue() ?? ''));

                    if (in_array($cellValue, $sectionTitles, true)) {
                        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'E2E8F0'],
                            ],
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_LEFT,
                                'vertical' => Alignment::VERTICAL_CENTER,
                            ],
                        ]);
                    }
                }

                foreach (range(1, $lastRow) as $row) {
                    $first = trim((string) ($sheet->getCell("A{$row}")->getValue() ?? ''));
                    $second = trim((string) ($sheet->getCell("B{$row}")->getValue() ?? ''));

                    if ($first === 'Nama Target' || $first === 'Tanggal') {
                        $endColumn = $first === 'Tanggal' ? 'G' : 'G';

                        $sheet->getStyle("A{$row}:{$endColumn}{$row}")->applyFromArray([
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
                    }

                    if ($second !== '' || $first === '-') {
                        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
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
                    }
                }

                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
            },
        ];
    }
}
