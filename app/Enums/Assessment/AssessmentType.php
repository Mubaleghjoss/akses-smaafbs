<?php

namespace App\Enums\Assessment;

enum AssessmentType: string
{
    case ASTS = 'asts';
    case ASAS = 'asas';
    case ASAT = 'asat';

    public function label(): string
    {
        return strtoupper($this->value);
    }

    /**
     * Nama panjang untuk judul halaman & dokumen rapor.
     */
    public function namaPanjang(): string
    {
        return match ($this) {
            self::ASTS => 'Asesmen Sumatif Tengah Semester',
            self::ASAS => 'Asesmen Sumatif Akhir Semester',
            self::ASAT => 'Asesmen Sumatif Akhir Tahun',
        };
    }

    /**
     * Semester yang boleh memuat jenis penilaian ini.
     *
     * ASAT hanya di semester GENAP (kebijakan sekolah: asesmen akhir tahun
     * dilaksanakan di akhir tahun ajaran). ASTS & ASAS berjalan di keduanya.
     *
     * @return array<int, string>  'ganjil' dan/atau 'genap'
     */
    public function semesterYangDiizinkan(): array
    {
        return match ($this) {
            self::ASAT => ['genap'],
            default => ['ganjil', 'genap'],
        };
    }

    /**
     * Kode jenis template rapor yang dipakai.
     *
     * ASAT belum punya template sendiri dan memakai template ASAS (keputusan
     * sekolah: bentuk rapornya sama). Begitu template berjenis 'asat' dibuat,
     * pemilih template akan memakainya lebih dulu tanpa mengubah kode ini —
     * lihat templateTypeCandidates().
     */
    public function tipeTemplateCadangan(): string
    {
        return match ($this) {
            self::ASAT => self::ASAS->value,
            default => $this->value,
        };
    }

    /**
     * Urutan pencarian template: jenis sendiri lebih dulu, lalu cadangan.
     *
     * @return array<int, string>
     */
    public function templateTypeCandidates(): array
    {
        $cadangan = $this->tipeTemplateCadangan();

        return $cadangan === $this->value
            ? [$this->value]
            : [$this->value, $cadangan];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }

    /**
     * Pilihan dengan nama panjang, untuk form & tab.
     *
     * @return array<string, string>
     */
    public static function optionsPanjang(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [
                $type->value => $type->label().' — '.$type->namaPanjang(),
            ])
            ->all();
    }
}
