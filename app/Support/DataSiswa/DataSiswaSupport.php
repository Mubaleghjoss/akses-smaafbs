<?php

namespace App\Support\DataSiswa;

use App\Models\DataSiswa;
use App\Models\Rombel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DataSiswaSupport
{
    protected static ?bool $tableAvailable = null;

    /**
     * @var array<int, string>|null
     */
    protected static ?array $exportableColumnsCache = null;

    /**
     * @var array<string, array<string, string>>
     */
    protected static array $profileOptionCache = [];

    /**
     * @return array<int, string>
     */
    public static function simpleProfileColumns(): array
    {
        return [
            'nama',
            'kepribadian',
            'gaya_belajar',
            'profiling',
            'mbti',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function importableColumns(): array
    {
        return array_values(array_filter(
            self::exportableColumns(),
            fn (string $column): bool => ! in_array($column, ['id', 'created_at', 'updated_at'], true),
        ));
    }

    /**
     * @return array<int, string>
     */
    public static function exportableColumns(): array
    {
        if (static::$exportableColumnsCache !== null) {
            return static::$exportableColumnsCache;
        }

        if (static::tableAvailable()) {
            $availableColumns = Schema::getColumnListing('data_siswa');

            return static::$exportableColumnsCache = static::orderColumns($availableColumns);
        }

        return static::$exportableColumnsCache = static::orderColumns([
            'id',
            'nama',
            'kepribadian',
            'gaya_belajar',
            'profiling',
            'mbti',
            'rombel_saat_ini',
            'billing_code',
            'wa_ortu',
            'nipd',
            'jk',
            'nisn',
            'tanggal_lahir',
            'status',
            'kategori_non_aktif',
            'alasan_non_aktif',
            'tanggal_non_aktif',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public static function templateRows(): array
    {
        $columns = self::importableColumns();
        $exampleValues = [
            'nama' => 'ABIEL KHIAR SHAHREZA',
            'kepribadian' => 'Plegmatis',
            'gaya_belajar' => 'Visual dan Kinestetik',
            'profiling' => 'Emotional Quotient (EQ)',
            'mbti' => 'ENTJ',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'billing_code' => 'AFBS-001',
            'wa_ortu' => '081234567890',
            'nipd' => '2025001',
            'jk' => 'L',
            'nisn' => '1234567890',
            'nik' => '3273011501100001',
            'no_kk' => '3273010101220001',
            'anak_ke' => '2',
            'jumlah_saudara' => '3',
            'tempat_lahir' => 'Bogor',
            'tanggal_lahir' => '2010-01-15',
            'agama' => 'Islam',
            'alamat' => 'Jl. Contoh No. 1',
            'rt' => '004',
            'rw' => '006',
            'dusun' => 'Cilebut Timur',
            'kelurahan' => 'Cilebut Barat',
            'kecamatan' => 'Sukaraja',
            'kode_pos' => '16710',
            'jenis_tinggal' => 'Bersama Orang Tua',
            'alat_transportasi' => 'Diantar Orang Tua',
            'jarak_rumah' => '4',
            'waktu_tempuh' => '20',
            'nama_ayah' => 'Bapak Contoh',
            'nik_ayah' => '3273010101800001',
            'tahun_lahir_ayah' => '1980',
            'pendidikan_ayah' => 'S1',
            'pekerjaan_ayah' => 'Wiraswasta',
            'penghasilan_ayah' => '3000000',
            'nama_ibu' => 'Ibu Contoh',
            'nik_ibu' => '3273010101850002',
            'tahun_lahir_ibu' => '1985',
            'pendidikan_ibu' => 'SMA',
            'pekerjaan_ibu' => 'Ibu Rumah Tangga',
            'penghasilan_ibu' => '1500000',
            'nama_wali' => 'Paman Contoh',
            'tahun_lahir_wali' => '1975',
            'pendidikan_wali' => 'SMA',
            'pekerjaan_wali' => 'Pedagang',
            'penghasilan_wali' => '2500000',
            'sekolah_asal' => 'SDIT AFBS',
            'no_akta_lahir' => 'AL-2010-00123',
            'no_kip' => 'KIP-0011223344',
            'kebutuhan_khusus' => 'Tidak',
            'tinggi_badan' => '150',
            'berat_badan' => '42',
            'lingkar_kepala' => '52',
            'jumlah_absen' => '0',
            'status' => 'aktif',
            'kategori_non_aktif' => 'mutasi',
            'alasan_non_aktif' => 'Mengikuti perpindahan domisili orang tua.',
            'tanggal_non_aktif' => '2025-07-15',
        ];

        return [
            $columns,
            array_map(
                fn (string $column): mixed => $exampleValues[$column] ?? null,
                $columns,
            ),
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public static function simpleProfileTemplateRows(): array
    {
        return [
            ['NO', 'NAMA', 'KEPRIBADIAN', 'GAYA BELAJAR', 'PROFILING', 'MBTI'],
            [1, 'ABIEL KHIAR SHAHREZA', 'PLEGMATIS', 'VISUAL DAN KINESTETIK', 'EMOTIONAL QUOTIENT (EQ)', 'ENTJ'],
            [2, 'ADAM FATHUR RISQI', 'PLEGMATIS', 'VISUAL', 'EMOTIONAL QUOTIENT (EQ)', 'INFP'],
            [3, 'ADI RIFKI MIKAL UTOMO', 'KOLERIS', 'KINESTETIK', 'INTELLIGENCE QUOTIENT (IQ)', 'ESTP'],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public static function simpleProfileExportRows(?User $user = null): array
    {
        $rows = [
            ['NO', 'NAMA', 'KEPRIBADIAN', 'GAYA BELAJAR', 'PROFILING', 'MBTI'],
        ];

        $students = self::baseQuery($user)
            ->orderBy('rombel_saat_ini')
            ->orderBy('nama')
            ->get(self::simpleProfileColumns());

        foreach ($students as $index => $student) {
            $rows[] = [
                $index + 1,
                Str::upper((string) ($student->nama ?? '')),
                Str::upper((string) ($student->kepribadian ?? '')),
                Str::upper((string) ($student->gaya_belajar ?? '')),
                Str::upper((string) ($student->profiling ?? '')),
                Str::upper((string) ($student->mbti ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public static function guideRows(): array
    {
        return [
            ['PETUNJUK IMPORT DATA SISWA'],
            ['1', 'Gunakan nama kolom persis seperti sheet template.'],
            ['2', 'Kolom minimal yang dianjurkan: nama, jk, rombel_saat_ini, status, nipd atau nisn.'],
            ['3', 'Jika nipd cocok dengan data lama, baris akan diperbarui. Jika tidak ada nipd tetapi nisn cocok, data juga diperbarui.'],
            ['4', 'Kolom kosong pada file import tidak akan menimpa isi lama saat update.'],
            ['5', 'Format tanggal_lahir yang aman: YYYY-MM-DD.'],
            ['6', 'Nilai status yang didukung: aktif, alumni, pindah, keluar.'],
            ['7', 'Jika status selain aktif, isi kategori_non_aktif, alasan_non_aktif, dan tanggal_non_aktif (YYYY-MM-DD).'],
            ['8', 'Nilai kategori_non_aktif yang didukung: lulus, mutasi, mengundurkan_diri, wafat, lainnya.'],
            ['9', 'Isi minimal salah satu identitas unik (nipd atau nisn). Disarankan isi keduanya agar update data lebih aman.'],
            ['10', 'Kolom bantuan/program (misalnya no_kip/no_kks/no_pkh) dan kolom fisik (tinggi/berat/lingkar_kepala) boleh diisi jika kolom tersedia.'],
            ['11', 'Anda juga bisa pakai format sederhana seperti sheet template_data_tes_siswa: NO, NAMA, KEPRIBADIAN, GAYA BELAJAR, PROFILING, MBTI.'],
            ['12', 'Kolom No pada format sederhana akan diabaikan saat import.'],
            ['13', 'Template ini otomatis mengikuti kolom yang tersedia pada tabel data_siswa saat ini.'],
            ['14', 'PENTING: Saat update, kolom yang dibiarkan kosong pada file import tidak akan menghapus nilai lama di database.'],
        ];
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    protected static function orderColumns(array $columns): array
    {
        $priority = [
            'id',
            'nama',
            'kepribadian',
            'gaya_belajar',
            'profiling',
            'mbti',
            'rombel_saat_ini',
            'billing_code',
            'wa_ortu',
            'nipd',
            'jk',
            'nisn',
            'tempat_lahir',
            'tanggal_lahir',
            'status',
            'kategori_non_aktif',
            'alasan_non_aktif',
            'tanggal_non_aktif',
            'created_at',
            'updated_at',
        ];

        $available = collect($columns)->values();
        $ordered = collect($priority)->intersect($available)->values();

        return $ordered
            ->concat($available->reject(fn (string $column): bool => $ordered->contains($column))->values())
            ->all();
    }

    public static function extractAngkatan(?string $rombel): ?string
    {
        if (blank($rombel)) {
            return null;
        }

        if (preg_match('/(20\d{2}[\/-]20\d{2})/', (string) $rombel, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function flushCachedOptions(): void
    {
        Cache::increment('data_siswa_support:options_version');

        static::$profileOptionCache = [];
    }

    /**
     * @return array<string, string>
     */
    public static function angkatanOptions(?User $user = null): array
    {
        return Cache::remember(
            'data_siswa_support:angkatan_options:v'.self::optionsCacheVersion().':'.self::scopeCacheKey($user),
            now()->addMinutes(10),
            function () use ($user): array {
                $fromStudents = self::baseQuery($user)
                    ->whereNotNull('rombel_saat_ini')
                    ->where('rombel_saat_ini', '!=', '')
                    ->pluck('rombel_saat_ini')
                    ->map(fn (?string $rombel): ?string => self::extractAngkatan($rombel));

                $fromMaster = self::canSeeMasterRombels($user)
                    ? collect(self::masterAngkatanValues())
                    : collect();

                return $fromMaster
                    ->merge($fromStudents)
                    ->filter()
                    ->unique()
                    ->sort()
                    ->mapWithKeys(fn (string $angkatan): array => [$angkatan => $angkatan])
                    ->all();
            },
        );
    }

    /**
     * @return array<int, string>
     */
    public static function rombelNamesForAngkatan(?string $angkatan, ?User $user = null): array
    {
        $normalized = Rombel::normalizeName($angkatan);

        if ($normalized === '' || ! Rombel::tableAvailable() || ! self::canSeeMasterRombels($user)) {
            return [];
        }

        return Cache::remember(
            'data_siswa_support:rombel_names_for_angkatan:v'.self::optionsCacheVersion().':'.$normalized,
            now()->addMinutes(10),
            fn (): array => Rombel::query()
                ->where('angkatan', $normalized)
                ->orderBy('nama')
                ->pluck('nama')
                ->map(fn (?string $rombel): string => Rombel::normalizeName($rombel))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        );
    }

    public static function angkatanLabelForRombel(?string $rombel): ?string
    {
        $normalized = Rombel::normalizeName($rombel);

        if ($normalized === '') {
            return null;
        }

        if (Rombel::tableAvailable()) {
            $fromMaster = self::masterRombelAngkatanMap();

            if (filled($fromMaster[$normalized] ?? null)) {
                return (string) $fromMaster[$normalized];
            }
        }

        return self::extractAngkatan($normalized);
    }

    /**
     * @return array<string, string>
     */
    public static function rombelOptions(?User $user = null): array
    {
        return Cache::remember(
            'data_siswa_support:rombel_options:v'.self::optionsCacheVersion().':'.self::scopeCacheKey($user),
            now()->addMinutes(10),
            function () use ($user): array {
                $fromStudents = self::baseQuery($user)
                    ->whereNotNull('rombel_saat_ini')
                    ->where('rombel_saat_ini', '!=', '')
                    ->orderBy('rombel_saat_ini')
                    ->pluck('rombel_saat_ini', 'rombel_saat_ini');

                $fromMaster = self::canSeeMasterRombels($user)
                    ? collect(self::masterRombelOptions())
                    : collect();

                return $fromMaster
                    ->merge($fromStudents)
                    ->filter()
                    ->sortKeys()
                    ->all();
            },
        );
    }

    /**
     * @return array<string, string>
     */
    public static function masterRombelOptions(bool $includeInactive = false): array
    {
        if (! Rombel::tableAvailable()) {
            return [];
        }

        return Rombel::query()
            ->when(! $includeInactive, fn (Builder $query): Builder => $query->where('is_active', true))
            ->orderBy('nama')
            ->pluck('nama', 'nama')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected static function masterAngkatanValues(): array
    {
        if (! Rombel::tableAvailable()) {
            return [];
        }

        return Rombel::query()
            ->whereNotNull('angkatan')
            ->where('angkatan', '!=', '')
            ->orderBy('angkatan')
            ->pluck('angkatan')
            ->map(fn (?string $angkatan): string => Rombel::normalizeName($angkatan))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function masterRombelAngkatanMap(): array
    {
        return Cache::remember(
            'data_siswa_support:rombel_angkatan_map:v'.self::optionsCacheVersion(),
            now()->addMinutes(10),
            fn (): array => Rombel::query()
                ->whereNotNull('angkatan')
                ->where('angkatan', '!=', '')
                ->pluck('angkatan', 'nama')
                ->mapWithKeys(fn (?string $angkatan, ?string $rombel): array => [
                    Rombel::normalizeName($rombel) => Rombel::normalizeName($angkatan),
                ])
                ->filter()
                ->all(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function profileOptions(string $column, ?User $user = null): array
    {
        $allowedColumns = [
            'kepribadian',
            'gaya_belajar',
            'profiling',
            'mbti',
        ];

        if (! in_array($column, $allowedColumns, true)) {
            return [];
        }

        $cacheKey = $column.':v'.self::optionsCacheVersion().':'.self::scopeCacheKey($user);

        if (array_key_exists($cacheKey, static::$profileOptionCache)) {
            return static::$profileOptionCache[$cacheKey];
        }

        return static::$profileOptionCache[$cacheKey] = Cache::remember(
            'data_siswa_support:profile_options:'.$cacheKey,
            now()->addMinutes(10),
            fn (): array => self::baseQuery($user)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->orderBy($column)
                ->pluck($column, $column)
                ->all(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function profileSuggestions(string $column, ?User $user = null): array
    {
        return collect(self::profileOptions($column, $user))
            ->keys()
            ->map(fn (string $value): string => Str::upper($value))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    protected static function baseQuery(?User $user = null): Builder
    {
        return DataSiswa::applyVisibleScope(DataSiswa::query(), $user);
    }

    protected static function canSeeMasterRombels(?User $user = null): bool
    {
        if (! $user) {
            return true;
        }

        $user->loadMissing('roles');

        return $user->hasFullAdminAccess()
            || $user->canManageModule('data_siswa')
            || $user->canManageModule('rombel');
    }

    protected static function optionsCacheVersion(): int
    {
        return (int) Cache::rememberForever('data_siswa_support:options_version', fn (): int => 1);
    }

    protected static function scopeCacheKey(?User $user = null): string
    {
        if (! $user) {
            return 'guest';
        }

        return sha1(json_encode([
            'id' => $user->getKey(),
            'roles' => $user->relationLoaded('roles')
                ? $user->roles->pluck('name')->values()->all()
                : $user->getRoleNames()->values()->all(),
            'boarding_rombel_scope' => $user->boardingRombelScopes(),
            'guru_walas_scope' => $user->guruWalasScopes(),
            'angkatan_scope' => $user->boardingAngkatanScope(),
            'guru_tendik_id' => $user->guru_tendik_id,
        ]));
    }

    protected static function tableAvailable(): bool
    {
        return static::$tableAvailable ??= Schema::hasTable('data_siswa');
    }
}
