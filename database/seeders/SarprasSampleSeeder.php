<?php

namespace Database\Seeders;

use App\Models\SarprasActivity;
use App\Models\SarprasBospInventory;
use App\Models\SarprasMonthlyAgenda;
use App\Models\SarprasRoomInventory;
use Illuminate\Database\Seeder;

class SarprasSampleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBospInventories();
        $this->seedRoomInventories();
        $this->seedActivities();
        $this->seedMonthlyAgendas();
    }

    private function seedBospInventories(): void
    {
        $rows = [
            [
                'nomor_urut' => '001',
                'nama_barang' => 'Proyektor Epson EB E500',
                'quality' => 1,
                'bulan_beli' => 3,
                'tahun_beli' => 2024,
                'kode_barang' => '001/PR/II/24',
                'lokasi_barang' => 'ruang guru',
                'tanggal_datang' => '2024-03-18',
                'total_harga' => 15704000,
                'catatan' => 'Data contoh sarpras dari format daftar BOSP.',
            ],
            [
                'nomor_urut' => '002',
                'nama_barang' => 'Proyektor Epson EB E500',
                'quality' => 1,
                'bulan_beli' => 3,
                'tahun_beli' => 2024,
                'kode_barang' => '002/PR/III/24',
                'lokasi_barang' => 'ruang guru',
                'tanggal_datang' => '2024-03-18',
                'total_harga' => 15704000,
                'catatan' => 'Data contoh sarpras dari format daftar BOSP.',
            ],
        ];

        foreach ($rows as $row) {
            SarprasBospInventory::updateOrCreate(
                ['kode_barang' => $row['kode_barang']],
                $row
            );
        }
    }

    private function seedRoomInventories(): void
    {
        $inventory = SarprasRoomInventory::updateOrCreate(
            [
                'nama_gedung' => 'SMA Al Furqon Boarding School',
                'nama_ruang' => 'Ruang Kelas 12 IPA dan 12 IPS',
                'nomor_ruang' => '18',
            ],
            [
                'tanggal_pendataan' => '2025-07-01',
                'penanggung_jawab' => 'Toha Nyono, S.Si',
                'diketahui_oleh' => 'Ahmad Tri Anggoro, S.T',
                'catatan' => 'Data contoh sarpras dari format inventaris ruangan.',
            ]
        );

        $inventory->items()->delete();

        $items = [
            ['nama_barang' => 'Meja Guru', 'jumlah' => 3, 'kondisi_barang' => 'Baik', 'keterangan' => null],
            ['nama_barang' => 'Bangku guru', 'jumlah' => 2, 'kondisi_barang' => 'Baik', 'keterangan' => null],
            ['nama_barang' => 'Kipas angin berdiri', 'jumlah' => 1, 'kondisi_barang' => 'Baik', 'keterangan' => null],
            ['nama_barang' => 'Sitroh', 'jumlah' => 3, 'kondisi_barang' => 'Baik', 'keterangan' => null],
            ['nama_barang' => 'Sitroh roda', 'jumlah' => 2, 'kondisi_barang' => 'Baik', 'keterangan' => null],
            ['nama_barang' => 'Bangku siswa lipat model kampus', 'jumlah' => 49, 'kondisi_barang' => 'Baik', 'keterangan' => 'rusak 1'],
            ['nama_barang' => 'Lampu', 'jumlah' => 25, 'kondisi_barang' => 'Baik', 'keterangan' => null],
            ['nama_barang' => 'Kipas angin', 'jumlah' => 3, 'kondisi_barang' => 'Baik', 'keterangan' => null],
            ['nama_barang' => 'Kipas angin dinding', 'jumlah' => 3, 'kondisi_barang' => 'Baik', 'keterangan' => null],
            ['nama_barang' => 'Galon Air Minum', 'jumlah' => 1, 'kondisi_barang' => 'Baik', 'keterangan' => null],
            ['nama_barang' => 'Meja Ngaji', 'jumlah' => 30, 'kondisi_barang' => 'Baik', 'keterangan' => null],
            ['nama_barang' => 'Jam dinding', 'jumlah' => 1, 'kondisi_barang' => 'Baik', 'keterangan' => null],
            ['nama_barang' => 'Jam digital', 'jumlah' => 1, 'kondisi_barang' => 'Baik', 'keterangan' => null],
            ['nama_barang' => 'Kran biasa', 'jumlah' => 2, 'kondisi_barang' => 'Baik', 'keterangan' => null],
            ['nama_barang' => 'Kran Otomatis', 'jumlah' => 2, 'kondisi_barang' => 'Baik', 'keterangan' => 'Kamar Mandi'],
            ['nama_barang' => 'Sandal bakaik', 'jumlah' => 2, 'kondisi_barang' => 'Baik', 'keterangan' => 'Kamar Mandi'],
            ['nama_barang' => 'Tong mandi', 'jumlah' => 2, 'kondisi_barang' => 'Baik', 'keterangan' => 'Kamar Mandi'],
            ['nama_barang' => 'Sikat WC', 'jumlah' => 2, 'kondisi_barang' => 'Baik', 'keterangan' => 'Kamar Mandi'],
        ];

        foreach ($items as $index => $item) {
            $inventory->items()->create([
                'urutan' => $index + 1,
                'tanggal' => '2025-07-01',
                ...$item,
            ]);
        }
    }

    private function seedActivities(): void
    {
        SarprasActivity::updateOrCreate(
            [
                'tanggal_pengerjaan' => '2026-03-18',
                'perbaikan' => 'Servis motor inventaris sekolah dikarenakan gas agak tersendat',
            ],
            [
                'penanggung_jawab' => 'Mr. Anggoro',
                'hasil_akhir' => 'Motor sudah di servis tune up dengan mengganti oli mesin, oli gardan, dan injektor',
                'foto_sebelum' => 'sarpras/kegiatan/sebelum/sample-motor-before.svg',
                'foto_sesudah' => 'sarpras/kegiatan/sesudah/sample-motor-after.svg',
                'pelaksana_paraf' => 'Mr. Anggoro',
                'catatan' => 'Data contoh sarpras dari format kegiatan sarpras.',
            ]
        );
    }

    private function seedMonthlyAgendas(): void
    {
        SarprasMonthlyAgenda::updateOrCreate(
            [
                'bulan_agenda' => '2026-04-01',
                'urutan' => 1,
                'jenis_kegiatan' => 'Penggantian kepala shower yang patah untuk cuci piring di SB Ar Rahman',
            ],
            [
                'tindak_lanjut_status' => SarprasMonthlyAgenda::STATUS_SUDAH,
                'penanggung_jawab' => 'Mr. Anggoro, Ms. Ria',
                'catatan' => 'Data contoh sarpras dari format agenda bulanan.',
            ]
        );
    }
}
