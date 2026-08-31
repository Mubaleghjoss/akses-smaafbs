<?php

namespace App\Support\Perpustakaan;

use App\Models\PerpustakaanLiterasiDispensation;
use App\Models\PerpustakaanLiterasiMaterial;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu-satunya sumber kebenaran untuk "siapa yang dihitung sebagai responden".
 *
 * Aturan yang ditegakkan di sini:
 *
 *   siswa_aktif     = data_siswa status=aktif pada rombel aktif
 *   dikecualikan    = punya dispensasi terkonfirmasi (izin | sakit | tes MT)
 *   basis_responden = siswa_aktif - dikecualikan          <-- penyebut
 *   mengisi         = punya response yang tidak di Sampah <-- pembilang
 *   belum_mengisi   = basis_responden - mengisi
 *   persen          = mengisi / basis_responden * 100
 *
 * Siswa berstatus izin/sakit/tes MT TIDAK PERNAH masuk pembilang maupun
 * penyebut. Jumlahnya tetap dilaporkan pada kunci `excluded_*` sebagai
 * informasi terpisah, bukan sebagai pengisi.
 */
final class LiteracyRespondentBase
{
    /**
     * Basis responden untuk satu materi, dipecah per kelas.
     *
     * @param  array<int, string>|null  $classes  Batasi ke kelas tertentu; null = semua rombel aktif.
     * @return array<string, mixed>
     */
    public static function forMaterial(
        PerpustakaanLiterasiMaterial $material,
        ?array $classes = null,
    ): array {
        return self::forMaterialIds([(int) $material->getKey()], $classes);
    }

    /**
     * Basis responden gabungan untuk sekumpulan materi.
     *
     * Setiap pasangan (materi, siswa) dihitung sebagai satu slot pengisian,
     * sehingga persentase selalu berada di rentang 0-100 walaupun rentang
     * tanggalnya memuat banyak materi.
     *
     * @param  array<int, int>  $materialIds
     * @param  array<int, string>|null  $classes
     * @param  Carbon|null  $responseStart  Batasi jawaban yang dihitung "mengisi" ke rentang ini.
     * @return array<string, mixed>
     */
    public static function forMaterialIds(
        array $materialIds,
        ?array $classes = null,
        ?Carbon $responseStart = null,
        ?Carbon $responseEnd = null,
    ): array {
        $materialIds = collect($materialIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (! self::tablesAvailable() || $materialIds === []) {
            return self::emptyResult(count($materialIds));
        }

        $classFilter = self::normalizeClassFilter($classes);

        $rows = DB::table('data_siswa as students')
            ->crossJoin('perpustakaan_literasi_materials as materials')
            ->leftJoin('perpustakaan_literasi_responses as responses', function (JoinClause $join) use ($responseStart, $responseEnd): void {
                $join->on('responses.data_siswa_id', '=', 'students.id')
                    ->on('responses.material_id', '=', 'materials.id');

                // Saat rentang diberikan, jawaban di luar rentang tidak dihitung
                // sebagai "mengisi" pada rekap rentang tersebut.
                if ($responseStart !== null && $responseEnd !== null) {
                    $join->whereBetween('responses.submitted_at', [$responseStart, $responseEnd]);
                }
            })
            ->leftJoin('perpustakaan_literasi_dispensations as dispensations', function (JoinClause $join): void {
                $join->on('dispensations.data_siswa_id', '=', 'students.id')
                    ->on('dispensations.material_id', '=', 'materials.id')
                    ->whereNull('dispensations.deleted_at')
                    ->whereNotNull('dispensations.confirmed_at');
            })
            ->whereIn('materials.id', $materialIds)
            ->whereNull('materials.deleted_at')
            ->where('students.status', 'aktif')
            ->whereNotNull('students.rombel_saat_ini')
            ->where('students.rombel_saat_ini', '!=', '')
            ->when(
                $classFilter !== null,
                fn (QueryBuilder $query): QueryBuilder => $query->whereIn('students.rombel_saat_ini', $classFilter),
            )
            ->orderBy('students.rombel_saat_ini')
            ->orderBy('students.nama')
            ->get([
                'students.id as student_id',
                'students.nama as name',
                'students.rombel_saat_ini as class',
                'materials.id as material_id',
                'materials.title as material_title',
                'responses.id as response_id',
                'responses.deleted_at as response_deleted_at',
                'responses.submitted_at as response_submitted_at',
                'dispensations.id as dispensation_id',
                'dispensations.reason as dispensation_reason',
                'dispensations.note as dispensation_note',
                'dispensations.confirmed_at as dispensation_confirmed_at',
            ]);

        return self::summarize($rows, count($materialIds));
    }

    /**
     * Materi yang termasuk dalam lingkup kategori dan rentang tanggal.
     *
     * Sebuah materi dianggap masuk lingkup bila dibuka pada rentang tersebut,
     * atau punya jawaban / penetapan dispensasi pada rentang tersebut.
     *
     * @return array<int, int>
     */
    public static function materialIdsInScope(
        ?string $programCategory,
        Carbon $start,
        Carbon $end,
    ): array {
        if (! Schema::hasTable('perpustakaan_literasi_materials')) {
            return [];
        }

        $hasDispensations = Schema::hasTable('perpustakaan_literasi_dispensations');

        return DB::table('perpustakaan_literasi_materials as materials')
            ->whereNull('materials.deleted_at')
            ->when(
                filled($programCategory),
                fn (QueryBuilder $query): QueryBuilder => $query->where('materials.program_category', $programCategory),
            )
            ->where(function (QueryBuilder $query) use ($start, $end, $hasDispensations): void {
                $query
                    ->whereBetween('materials.opens_at', [$start, $end])
                    ->orWhereExists(function (QueryBuilder $sub) use ($start, $end): void {
                        $sub->from('perpustakaan_literasi_responses as responses')
                            ->whereColumn('responses.material_id', 'materials.id')
                            ->whereNull('responses.deleted_at')
                            ->whereBetween('responses.submitted_at', [$start, $end]);
                    });

                if ($hasDispensations) {
                    $query->orWhereExists(function (QueryBuilder $sub) use ($start, $end): void {
                        $sub->from('perpustakaan_literasi_dispensations as dispensations')
                            ->whereColumn('dispensations.material_id', 'materials.id')
                            ->whereNull('dispensations.deleted_at')
                            ->whereBetween('dispensations.confirmed_at', [$start, $end]);
                    });
                }
            })
            ->orderBy('materials.opens_at')
            ->orderBy('materials.id')
            ->pluck('materials.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Nama rombel aktif; dipakai sebagai acuan kelas pada semua panel.
     *
     * @return array<int, string>
     */
    public static function activeClassNames(): array
    {
        if (Schema::hasTable('rombels')) {
            $fromRombel = DB::table('rombels')
                ->where('is_active', true)
                ->orderBy('nama')
                ->pluck('nama')
                ->map(fn ($nama): string => trim((string) $nama))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($fromRombel !== []) {
                return $fromRombel;
            }
        }

        if (! Schema::hasTable('data_siswa')) {
            return [];
        }

        return DB::table('data_siswa')
            ->where('status', 'aktif')
            ->whereNotNull('rombel_saat_ini')
            ->where('rombel_saat_ini', '!=', '')
            ->distinct()
            ->orderBy('rombel_saat_ini')
            ->pluck('rombel_saat_ini')
            ->map(fn ($nama): string => trim((string) $nama))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, mixed>
     */
    protected static function summarize(Collection $rows, int $materialCount): array
    {
        $classes = [];
        $studentsWhoFilled = [];
        $studentsExcluded = [];
        $materials = [];

        foreach ($rows as $row) {
            $class = trim((string) $row->class) ?: '-';

            if (! isset($classes[$class])) {
                $classes[$class] = self::emptyClassBucket($class);
            }

            $bucket = &$classes[$class];
            $studentId = (int) $row->student_id;
            $bucket['student_ids'][$studentId] = true;
            // Satu baris = satu slot (siswa x materi). Slot dan basis responden
            // harus memakai satuan yang sama, kalau tidak persentasenya melar.
            $bucket['active_total']++;
            $bucket['material_ids'][(int) $row->material_id] = true;
            $materials[(int) $row->material_id] = (string) $row->material_title;

            $student = [
                'student_id' => $studentId,
                'name' => (string) $row->name,
                'class' => $class,
                'material_id' => (int) $row->material_id,
                'material_title' => (string) $row->material_title,
            ];

            $hasLiveResponse = $row->response_id !== null && $row->response_deleted_at === null;
            $hasTrashedResponse = $row->response_id !== null && $row->response_deleted_at !== null;
            // Dispensasi terkonfirmasi selalu menang atas response. Keadaan
            // keduanya bersamaan adalah anomali data, tetapi slot tetap tidak
            // boleh masuk pembilang maupun penyebut.
            $isExcluded = $row->dispensation_id !== null;

            if ($isExcluded) {
                $reason = (string) $row->dispensation_reason;
                $bucket['excluded_total']++;
                $bucket['excluded_by_reason'][$reason] = ($bucket['excluded_by_reason'][$reason] ?? 0) + 1;
                $bucket['excluded_students'][] = $student + [
                    'reason' => $reason,
                    'reason_label' => PerpustakaanLiterasiDispensation::reasonOptions()[$reason] ?? 'Dispensasi',
                    'note' => $row->dispensation_note !== null ? (string) $row->dispensation_note : null,
                    'confirmed_at' => $row->dispensation_confirmed_at !== null
                        ? Carbon::parse($row->dispensation_confirmed_at)->format('d/m/Y H:i')
                        : null,
                ];
                $studentsExcluded[$studentId] = true;

                continue;
            }

            // Mulai dari sini siswa masuk basis responden.
            $bucket['respondent_base']++;

            if ($hasLiveResponse) {
                $bucket['completed_total']++;
                $studentsWhoFilled[$studentId] = true;
                // Tanggal pengisian dipakai halaman rincian harian untuk
                // menghitung siapa yang belum mengisi sampai hari tertentu.
                $bucket['completed_students'][] = $student + [
                    'submitted_at' => $row->response_submitted_at !== null
                        ? Carbon::parse($row->response_submitted_at)
                        : null,
                ];

                continue;
            }

            if ($hasTrashedResponse) {
                $bucket['trashed_total']++;
                $bucket['trashed_students'][] = $student;

                continue;
            }

            $bucket['missing_total']++;
            $bucket['missing_students'][] = $student;
        }

        unset($bucket);

        $classes = collect($classes)
            ->map(function (array $bucket): array {
                $bucket['unique_students'] = count($bucket['student_ids']);
                unset($bucket['student_ids']);

                // Jumlah materi yang wajib diisi kelas ini pada rentang aktif.
                // Dipakai panel partisipasi untuk menjelaskan "100% itu dari
                // berapa materi".
                $bucket['material_count'] = count($bucket['material_ids']);
                $bucket['material_ids'] = array_keys($bucket['material_ids']);

                $bucket['participation_percentage'] = $bucket['respondent_base'] > 0
                    ? round(($bucket['completed_total'] / $bucket['respondent_base']) * 100, 1)
                    : null;
                $bucket['ratio'] = $bucket['completed_total'].'/'.$bucket['respondent_base'];

                return $bucket;
            })
            ->sortBy('class', SORT_NATURAL)
            ->values()
            ->all();

        $respondentBase = (int) collect($classes)->sum('respondent_base');
        $completedTotal = (int) collect($classes)->sum('completed_total');
        $excludedByReason = [];

        foreach ($classes as $class) {
            foreach ($class['excluded_by_reason'] as $reason => $total) {
                $excludedByReason[$reason] = ($excludedByReason[$reason] ?? 0) + $total;
            }
        }

        return [
            'material_count' => $materialCount,
            // Materi yang benar-benar muncul pada slot (siswa x materi) di
            // rentang ini, beserta judulnya untuk keperluan tampilan.
            'materials' => collect($materials)
                ->map(fn (string $title, int $id): array => ['id' => $id, 'title' => $title])
                ->sortBy('title', SORT_NATURAL)
                ->values()
                ->all(),
            'active_total' => (int) collect($classes)->sum('active_total'),
            'excluded_total' => (int) collect($classes)->sum('excluded_total'),
            'excluded_by_reason' => $excludedByReason,
            'respondent_base' => $respondentBase,
            'completed_total' => $completedTotal,
            'missing_total' => (int) collect($classes)->sum('missing_total'),
            'trashed_total' => (int) collect($classes)->sum('trashed_total'),
            'participation_percentage' => $respondentBase > 0
                ? round(($completedTotal / $respondentBase) * 100, 1)
                : null,
            'ratio' => $completedTotal.'/'.$respondentBase,
            'unique_students_filled' => count($studentsWhoFilled),
            'unique_students_excluded' => count($studentsExcluded),
            'classes' => $classes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function emptyClassBucket(string $class): array
    {
        return [
            'class' => $class,
            'student_ids' => [],
            'unique_students' => 0,
            'material_ids' => [],
            'material_count' => 0,
            'active_total' => 0,
            'excluded_total' => 0,
            'excluded_by_reason' => [],
            'respondent_base' => 0,
            'completed_total' => 0,
            'missing_total' => 0,
            'trashed_total' => 0,
            'excluded_students' => [],
            'missing_students' => [],
            'trashed_students' => [],
            'completed_students' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function emptyResult(int $materialCount = 0): array
    {
        return [
            'material_count' => $materialCount,
            'materials' => [],
            'active_total' => 0,
            'excluded_total' => 0,
            'excluded_by_reason' => [],
            'respondent_base' => 0,
            'completed_total' => 0,
            'missing_total' => 0,
            'trashed_total' => 0,
            'participation_percentage' => null,
            'ratio' => '0/0',
            'unique_students_filled' => 0,
            'unique_students_excluded' => 0,
            'classes' => [],
        ];
    }

    /**
     * @param  array<int, string>|null  $classes
     * @return array<int, string>|null
     */
    protected static function normalizeClassFilter(?array $classes): ?array
    {
        if ($classes === null) {
            return self::activeClassNames() ?: null;
        }

        $normalized = collect($classes)
            ->map(fn ($class): string => trim((string) $class))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $normalized === [] ? null : $normalized;
    }

    protected static function tablesAvailable(): bool
    {
        return Schema::hasTable('data_siswa')
            && Schema::hasTable('perpustakaan_literasi_materials')
            && Schema::hasTable('perpustakaan_literasi_responses')
            && Schema::hasTable('perpustakaan_literasi_dispensations');
    }
}
