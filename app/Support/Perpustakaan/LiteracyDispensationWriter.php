<?php

namespace App\Support\Perpustakaan;

use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiDispensation;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya tempat penulisan dispensasi literasi.
 *
 * Controller lama dan halaman kelola dispensasi memakai kelas ini agar aturan
 * "hanya siswa aktif" dan "tidak boleh menimpa jawaban" tidak bercabang dua.
 */
class LiteracyDispensationWriter
{
    /**
     * Tetapkan dispensasi untuk satu siswa pada satu materi.
     *
     * @throws ValidationException
     */
    public static function assign(
        PerpustakaanLiterasiMaterial $material,
        DataSiswa $student,
        string $reason,
        ?string $note,
        ?User $actor,
    ): PerpustakaanLiterasiDispensation {
        static::guardReason($reason, $note);

        return DB::transaction(function () use ($material, $student, $reason, $note, $actor): PerpustakaanLiterasiDispensation {
            $lockedStudent = DataSiswa::query()
                ->lockForUpdate()
                ->findOrFail($student->getKey());

            if ($lockedStudent->status !== 'aktif') {
                throw ValidationException::withMessages([
                    'student' => 'Dispensasi hanya dapat diberikan kepada siswa aktif.',
                ]);
            }

            $hasResponse = PerpustakaanLiterasiResponse::withTrashed()
                ->where('material_id', $material->getKey())
                ->where('data_siswa_id', $lockedStudent->getKey())
                ->exists();

            if ($hasResponse) {
                throw ValidationException::withMessages([
                    'student' => 'Siswa sudah memiliki jawaban aktif atau jawaban di Sampah.',
                ]);
            }

            $dispensation = PerpustakaanLiterasiDispensation::withTrashed()
                ->firstOrNew([
                    'material_id' => $material->getKey(),
                    'data_siswa_id' => $lockedStudent->getKey(),
                ]);

            $dispensation->forceFill([
                'reason' => $reason,
                'student_name_snapshot' => trim((string) $lockedStudent->nama),
                'student_class_snapshot' => trim((string) $lockedStudent->rombel_saat_ini) ?: null,
                'confirmed_by' => $actor?->getKey(),
                'confirmed_at' => now(),
                'note' => filled($note) ? trim((string) $note) : null,
                'deleted_at' => null,
            ])->save();

            return $dispensation;
        });
    }

    /**
     * Ubah alasan/keterangan dispensasi yang sudah ada.
     *
     * @throws ValidationException
     */
    public static function update(
        PerpustakaanLiterasiDispensation $dispensation,
        string $reason,
        ?string $note,
        ?User $actor,
    ): PerpustakaanLiterasiDispensation {
        static::guardReason($reason, $note);

        $dispensation->forceFill([
            'reason' => $reason,
            'note' => filled($note) ? trim((string) $note) : null,
            'confirmed_by' => $actor?->getKey(),
            'confirmed_at' => now(),
        ])->save();

        return $dispensation;
    }

    /**
     * Tetapkan satu alasan untuk banyak siswa x banyak materi sekaligus.
     *
     * Gagal-tertutup per baris: satu siswa yang sudah punya jawaban tidak
     * membatalkan siswa lain, tetapi dilaporkan sebagai baris yang dilewati.
     *
     * @param  array<int, int>  $studentIds
     * @param  array<int, int>  $materialIds
     * @return array{applied: int, skipped: array<int, string>}
     */
    public static function assignBulk(
        array $studentIds,
        array $materialIds,
        string $reason,
        ?string $note,
        ?User $actor,
    ): array {
        static::guardReason($reason, $note);

        if ($studentIds === [] || $materialIds === []) {
            throw ValidationException::withMessages([
                'bulk' => 'Pilih minimal satu siswa dan satu materi.',
            ]);
        }

        $students = DataSiswa::query()
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy(fn (DataSiswa $student): int => (int) $student->getKey());

        $materials = PerpustakaanLiterasiMaterial::query()
            ->whereIn('id', $materialIds)
            ->get()
            ->keyBy(fn (PerpustakaanLiterasiMaterial $material): int => (int) $material->getKey());

        $applied = 0;
        $skipped = [];

        foreach ($students as $student) {
            foreach ($materials as $material) {
                try {
                    static::assign($material, $student, $reason, $note, $actor);
                    $applied++;
                } catch (ValidationException $exception) {
                    $skipped[] = $student->nama.' — '.$material->title.': '
                        .collect($exception->errors())->flatten()->first();
                }
            }
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /**
     * @throws ValidationException
     */
    protected static function guardReason(string $reason, ?string $note): void
    {
        if (! array_key_exists($reason, PerpustakaanLiterasiDispensation::reasonOptions())) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan dispensasi tidak dikenal.',
            ]);
        }

        // Izin menuntut keterangan tertulis; sakit dan tes MT tidak.
        if ($reason === PerpustakaanLiterasiDispensation::REASON_PERMISSION) {
            if (blank($note) || mb_strlen(trim((string) $note)) < 5) {
                throw ValidationException::withMessages([
                    'note' => 'Keterangan izin wajib ditulis, minimal 5 karakter.',
                ]);
            }
        }

        if (filled($note) && mb_strlen(trim((string) $note)) > 1000) {
            throw ValidationException::withMessages([
                'note' => 'Keterangan maksimal 1000 karakter.',
            ]);
        }
    }
}
