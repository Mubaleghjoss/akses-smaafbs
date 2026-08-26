<?php

namespace App\Support\SpmbSync;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SpmbStudentMapper
{
    /**
     * @var array<string, true>|null
     */
    private ?array $availableColumns = null;

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public function map(array $source): array
    {
        $payload = [
            'nama' => data_get($source, 'biodata.nama'),
            'jk' => data_get($source, 'biodata.jenis_kelamin'),
            'nisn' => data_get($source, 'biodata.nisn'),
            'tempat_lahir' => data_get($source, 'biodata.tempat_lahir'),
            'tanggal_lahir' => data_get($source, 'biodata.tanggal_lahir'),
            'agama' => data_get($source, 'biodata.agama'),
            'alamat' => data_get($source, 'biodata.alamat'),
            'kelurahan' => data_get($source, 'biodata.kelurahan'),
            'kecamatan' => data_get($source, 'biodata.kecamatan'),
            'kota' => data_get($source, 'biodata.kota'),
            'provinsi' => data_get($source, 'biodata.provinsi'),
            'email' => data_get($source, 'biodata.email'),
            'wa_ortu' => $this->firstFilled([
                data_get($source, 'orang_tua.telepon_ayah'),
                data_get($source, 'orang_tua.telepon_ibu'),
                data_get($source, 'biodata.telepon'),
            ]),
            'nama_ayah' => data_get($source, 'orang_tua.nama_ayah'),
            'pendidikan_ayah' => data_get($source, 'orang_tua.pendidikan_ayah'),
            'pekerjaan_ayah' => data_get($source, 'orang_tua.pekerjaan_ayah'),
            'nama_ibu' => data_get($source, 'orang_tua.nama_ibu'),
            'pendidikan_ibu' => data_get($source, 'orang_tua.pendidikan_ibu'),
            'pekerjaan_ibu' => data_get($source, 'orang_tua.pekerjaan_ibu'),
            'sekolah_asal' => data_get($source, 'sekolah_asal.nama'),
            'alamat_sekolah' => data_get($source, 'sekolah_asal.alamat'),
            // Kontak siswa: SPMB mengirim nomor siswa & telepon rumah.
            // wa_ortu tetap memakai nomor orang tua (lihat di atas).
            'hp' => data_get($source, 'biodata.telepon'),
            'telepon' => $this->firstFilled([
                $this->bersih(data_get($source, 'biodata.telepon_rumah')),
                data_get($source, 'biodata.telepon'),
            ]),
            // Data keluarga & wilayah (api 1.2)
            'jml_saudara_kandung' => data_get($source, 'biodata.jumlah_saudara'),
            'dusun' => data_get($source, 'biodata.desa'),
            'tinggi_badan' => data_get($source, 'fisik.tinggi_badan'),
            'berat_badan' => data_get($source, 'fisik.berat_badan'),
            'lingkar_kepala' => data_get($source, 'fisik.lingkar_kepala'),
            'kepribadian' => $this->upper(data_get($source, 'hasil_tes.kepribadian')),
            'gaya_belajar' => $this->upper(data_get($source, 'hasil_tes.gaya_belajar')),
            'profiling' => $this->upper(data_get($source, 'hasil_tes.profiling')),
            'mbti' => $this->upper(data_get($source, 'hasil_tes.mbti')),
        ];

        $available = $this->availableColumns ??= array_fill_keys(
            Schema::getColumnListing('data_siswa'),
            true,
        );

        return collect($payload)
            ->filter(fn (mixed $value, string $column): bool => array_key_exists($column, $available)
                && $value !== null
                && $value !== '')
            ->all();
    }

    private function firstFilled(array $values): mixed
    {
        return collect($values)->first(fn (mixed $value): bool => filled($value));
    }

    private function upper(mixed $value): ?string
    {
        return filled($value) ? Str::upper(trim((string) $value)) : null;
    }

    /**
     * Buang nilai placeholder dari formulir SPMB ('-', 'n/a', '0', dsb) supaya
     * tidak tersimpan sebagai data palsu di app.
     */
    private function bersih(mixed $value): mixed
    {
        if (! filled($value)) {
            return null;
        }

        $bersih = trim((string) $value);

        return in_array(Str::lower($bersih), ['-', '--', 'n/a', 'na', 'tidak ada', '0'], true)
            ? null
            : $bersih;
    }
}
