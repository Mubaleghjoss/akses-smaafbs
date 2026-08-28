<?php

namespace App\Support\Bk;

use App\Models\BkKasus;
use App\Models\Rombel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BkSigapRecap
{
    /**
     * Hitung rekap laporan SIGAP pada satu rentang tanggal.
     *
     * Struktur hasil:
     *  - kelas_terdampak: daftar kelas yang punya catatan, beserta siswa & kasusnya
     *  - kelas_bersih: kelas aktif yang tidak punya catatan sama sekali
     *  - ringkasan: angka total untuk kartu statistik
     *
     * @return array{
     *     periode: array{dari: string, sampai: string},
     *     ringkasan: array<string, int>,
     *     kelas_terdampak: array<int, array<string, mixed>>,
     *     kelas_bersih: array<int, string>,
     *     kelas_tanpa_master: array<int, string>
     * }
     */
    public static function build(?string $dari, ?string $sampai, ?string $kategori = null, ?string $tingkat = null): array
    {
        [$start, $end] = static::normalizeRange($dari, $sampai);

        $empty = [
            'periode' => ['dari' => $start->toDateString(), 'sampai' => $end->toDateString()],
            'ringkasan' => [
                'total_kasus' => 0,
                'total_siswa' => 0,
                'total_keterlibatan' => 0,
                'kelas_terdampak' => 0,
                'kelas_bersih' => 0,
                'kelas_aktif' => 0,
                'belum_ditindak' => 0,
                'selesai' => 0,
            ],
            'kelas_terdampak' => [],
            'kelas_bersih' => [],
            'kelas_tanpa_master' => [],
        ];

        if (! Schema::hasTable('bk_kasus') || ! Schema::hasTable('bk_kasus_siswa') || ! Schema::hasTable('data_siswa')) {
            return $empty;
        }

        $kelasAktif = static::activeClassNames();
        $empty['kelas_bersih'] = $kelasAktif;
        $empty['ringkasan']['kelas_aktif'] = count($kelasAktif);
        $empty['ringkasan']['kelas_bersih'] = count($kelasAktif);

        $rows = DB::table('bk_kasus_siswa as pivot')
            ->join('bk_kasus as kasus', 'kasus.id', '=', 'pivot.bk_kasus_id')
            ->join('data_siswa as siswa', 'siswa.id', '=', 'pivot.siswa_id')
            ->whereDate('kasus.tanggal_kasus', '>=', $start->toDateString())
            ->whereDate('kasus.tanggal_kasus', '<=', $end->toDateString())
            ->when(filled($kategori), fn ($query) => $query->where('kasus.kategori', $kategori))
            ->when(filled($tingkat), fn ($query) => $query->where('kasus.tingkat', $tingkat))
            ->orderBy('kasus.tanggal_kasus')
            ->orderBy('siswa.nama')
            ->get([
                'kasus.id as kasus_id',
                'kasus.tanggal_kasus',
                'kasus.judul_kasus',
                'kasus.keterangan_kasus',
                'kasus.kategori',
                'kasus.tingkat',
                'kasus.tindak_lanjut',
                'kasus.status_tindak_lanjut',
                'siswa.id as siswa_id',
                'siswa.nama as nama_siswa',
                'siswa.rombel_saat_ini',
                'pivot.rombel_snapshot',
            ]);

        if ($rows->isEmpty()) {
            return $empty;
        }

        $kelasBuckets = [];
        $kasusIds = [];
        $siswaIds = [];
        $statusPerKasus = [];

        foreach ($rows as $row) {
            $kelas = Rombel::normalizeName($row->rombel_snapshot ?: $row->rombel_saat_ini);
            $kelas = $kelas !== '' ? $kelas : 'Tanpa Kelas';

            $kasusIds[$row->kasus_id] = true;
            $siswaIds[$row->siswa_id] = true;
            $statusPerKasus[$row->kasus_id] = $row->status_tindak_lanjut;

            $kelasBuckets[$kelas] ??= [
                'kelas' => $kelas,
                'siswa' => [],
                'kasus' => [],
            ];

            $kelasBuckets[$kelas]['siswa'][$row->siswa_id] ??= [
                'id' => $row->siswa_id,
                'nama' => $row->nama_siswa,
                'jumlah_kasus' => 0,
                'kasus' => [],
            ];

            $kelasBuckets[$kelas]['siswa'][$row->siswa_id]['jumlah_kasus']++;
            $kelasBuckets[$kelas]['siswa'][$row->siswa_id]['kasus'][] = [
                'id' => $row->kasus_id,
                'tanggal' => $row->tanggal_kasus,
                'judul' => $row->judul_kasus,
                'kategori' => BkKasus::kategoriLabel($row->kategori),
                'tingkat' => BkKasus::tingkatLabel($row->tingkat),
                'status' => BkKasus::statusLabel($row->status_tindak_lanjut),
            ];

            $kelasBuckets[$kelas]['kasus'][$row->kasus_id] ??= [
                'id' => $row->kasus_id,
                'tanggal' => $row->tanggal_kasus,
                'judul' => $row->judul_kasus,
                'keterangan' => $row->keterangan_kasus,
                'kategori' => BkKasus::kategoriLabel($row->kategori),
                'tingkat' => BkKasus::tingkatLabel($row->tingkat),
                'tindak_lanjut' => $row->tindak_lanjut,
                'status' => $row->status_tindak_lanjut,
                'status_label' => BkKasus::statusLabel($row->status_tindak_lanjut),
                'siswa' => [],
            ];

            $kelasBuckets[$kelas]['kasus'][$row->kasus_id]['siswa'][] = $row->nama_siswa;
        }

        $kelasTerdampak = collect($kelasBuckets)
            ->map(function (array $bucket): array {
                $siswa = collect($bucket['siswa'])
                    ->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();

                $kasus = collect($bucket['kasus'])
                    ->sortBy('tanggal')
                    ->values()
                    ->all();

                return [
                    'kelas' => $bucket['kelas'],
                    'jumlah_siswa' => count($siswa),
                    'jumlah_kasus' => count($kasus),
                    'siswa' => $siswa,
                    'kasus' => $kasus,
                ];
            })
            ->sortBy('kelas', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $namaKelasTerdampak = array_column($kelasTerdampak, 'kelas');

        $kelasBersih = array_values(array_diff($kelasAktif, $namaKelasTerdampak));
        $kelasTanpaMaster = array_values(array_diff($namaKelasTerdampak, $kelasAktif));

        $statusCounts = collect($statusPerKasus);

        return [
            'periode' => ['dari' => $start->toDateString(), 'sampai' => $end->toDateString()],
            'ringkasan' => [
                'total_kasus' => count($kasusIds),
                'total_siswa' => count($siswaIds),
                'total_keterlibatan' => $rows->count(),
                'kelas_terdampak' => count($kelasTerdampak),
                'kelas_bersih' => count($kelasBersih),
                'kelas_aktif' => count($kelasAktif),
                'belum_ditindak' => $statusCounts->filter(fn ($status): bool => $status === BkKasus::STATUS_BELUM)->count(),
                'selesai' => $statusCounts->filter(fn ($status): bool => $status === BkKasus::STATUS_SELESAI)->count(),
            ],
            'kelas_terdampak' => $kelasTerdampak,
            'kelas_bersih' => $kelasBersih,
            'kelas_tanpa_master' => $kelasTanpaMaster,
        ];
    }

    /**
     * Daftar kelas aktif sebagai acuan "kelas tanpa kasus".
     * Sumber utama tabel rombels (is_active), fallback ke rombel siswa aktif.
     *
     * @return array<int, string>
     */
    public static function activeClassNames(): array
    {
        $fromRombel = [];

        if (Rombel::tableAvailable()) {
            $fromRombel = Rombel::query()
                ->where('is_active', true)
                ->orderBy('nama')
                ->pluck('nama')
                ->map(fn ($nama): string => Rombel::normalizeName($nama))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if ($fromRombel !== []) {
            return $fromRombel;
        }

        if (! Schema::hasTable('data_siswa')) {
            return [];
        }

        return DB::table('data_siswa')
            ->where('status', 'aktif')
            ->whereNotNull('rombel_saat_ini')
            ->distinct()
            ->orderBy('rombel_saat_ini')
            ->pluck('rombel_saat_ini')
            ->map(fn ($nama): string => Rombel::normalizeName($nama))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function normalizeRange(?string $dari, ?string $sampai): array
    {
        $start = filled($dari) ? Carbon::parse($dari)->startOfDay() : now()->startOfMonth();
        $end = filled($sampai) ? Carbon::parse($sampai)->startOfDay() : now()->endOfMonth()->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }
}
