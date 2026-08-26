<?php

namespace App\Support\Assessment\Reporting;

use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\StudentSubjectResult;
use Illuminate\Support\Collection;

/**
 * Memeriksa KELENGKAPAN NILAI sebelum rapor dicetak.
 *
 * Dua hal yang SENGAJA dipisahkan, karena tindak lanjutnya berbeda:
 *
 *   1. BELUM DIISI    -> gurunya belum memasukkan nilai (data benar-benar
 *                        kosong). Yang perlu ditagih: guru pengampu.
 *                        Inilah yang membuat rapor bertanda SEMENTARA dan
 *                        nilainya ditulis "(belum diisi)".
 *
 *   2. BELUM DIVERIFIKASI -> nilainya SUDAH ada, hanya menunggu persetujuan.
 *                        Yang perlu ditagih: kurikulum/admin. Rapor tetap
 *                        lengkap isinya, jadi TIDAK dianggap sementara.
 *
 * Mencampur keduanya membuat rapor yang isinya sudah utuh ikut bertanda
 * sementara — menyesatkan dan membuat penanda kehilangan makna.
 */
class AssessmentReportCompleteness
{
    /**
     * Periksa satu siswa.
     *
     * @return array{
     *   sementara: bool,
     *   total_mapel: int,
     *   mapel_belum_diisi: array<int, array<string, mixed>>,
     *   mapel_belum_verifikasi: array<int, array<string, mixed>>,
     *   jumlah_belum_diisi: int,
     *   jumlah_belum_verifikasi: int,
     *   ringkasan: string
     * }
     */
    public function untukSiswa(AssessmentPeriodStudent $siswa): array
    {
        $assignments = $this->assignmentsRombel(
            (int) $siswa->assessment_period_id,
            (int) $siswa->assessment_period_rombel_id,
        );

        // Nilai akhir yang sudah terhitung untuk siswa ini.
        $nilaiTerisi = StudentSubjectResult::query()
            ->where('assessment_period_student_id', $siswa->getKey())
            ->whereNotNull('final_score')
            ->pluck('final_score', 'assessment_period_assignment_id');

        $belumDiisi = [];
        $belumVerifikasi = [];

        foreach ($assignments as $a) {
            $adaNilai = $nilaiTerisi->has($a->getKey());

            if (! $adaNilai) {
                $belumDiisi[] = $this->baris($a);

                continue;
            }

            if (! $this->sudahDiverifikasi($a)) {
                $belumVerifikasi[] = $this->baris($a);
            }
        }

        return $this->hasil($assignments->count(), $belumDiisi, $belumVerifikasi);
    }

    /**
     * Periksa satu rombel (dipakai popup sebelum cetak massal).
     *
     * @return array{
     *   sementara: bool,
     *   total_mapel: int,
     *   total_siswa: int,
     *   mapel_belum_diisi: array<int, array<string, mixed>>,
     *   mapel_belum_verifikasi: array<int, array<string, mixed>>,
     *   jumlah_belum_diisi: int,
     *   jumlah_belum_verifikasi: int,
     *   ringkasan: string
     * }
     */
    public function untukRombel(int $periodId, int $rombelId): array
    {
        $assignments = $this->assignmentsRombel($periodId, $rombelId);

        $siswaIds = AssessmentPeriodStudent::query()
            ->where('assessment_period_id', $periodId)
            ->where('assessment_period_rombel_id', $rombelId)
            ->where('is_active', true)
            ->pluck('id');

        $jumlahSiswa = $siswaIds->count();

        // Berapa siswa yang sudah punya nilai akhir, per assignment.
        $terisiPerAssignment = StudentSubjectResult::query()
            ->whereIn('assessment_period_student_id', $siswaIds->all() ?: [0])
            ->whereNotNull('final_score')
            ->selectRaw('assessment_period_assignment_id, COUNT(*) as jumlah')
            ->groupBy('assessment_period_assignment_id')
            ->pluck('jumlah', 'assessment_period_assignment_id');

        $belumDiisi = [];
        $belumVerifikasi = [];

        foreach ($assignments as $a) {
            $terisi = (int) ($terisiPerAssignment[$a->getKey()] ?? 0);

            if ($terisi < $jumlahSiswa) {
                $belumDiisi[] = $this->baris($a, $jumlahSiswa - $terisi, $jumlahSiswa);

                continue;
            }

            if (! $this->sudahDiverifikasi($a)) {
                $belumVerifikasi[] = $this->baris($a);
            }
        }

        return [
            ...$this->hasil($assignments->count(), $belumDiisi, $belumVerifikasi),
            'total_siswa' => $jumlahSiswa,
        ];
    }

    /**
     * Nilai siap tampil di rapor, atau penanda "(belum diisi)".
     */
    public function labelNilai(mixed $nilai): string
    {
        return ($nilai === null || $nilai === '')
            ? '(belum diisi)'
            : (string) $nilai;
    }

    /**
     * @return Collection<int, AssessmentPeriodAssignment>
     */
    private function assignmentsRombel(int $periodId, int $rombelId): Collection
    {
        return AssessmentPeriodAssignment::query()
            ->where('assessment_period_id', $periodId)
            ->where('assessment_period_rombel_id', $rombelId)
            ->orderBy('subject_name_snapshot')
            ->get();
    }

    private function sudahDiverifikasi(AssessmentPeriodAssignment $a): bool
    {
        $status = $a->status instanceof AssignmentStatus
            ? $a->status
            : AssignmentStatus::tryFrom((string) $a->status);

        return in_array($status, [AssignmentStatus::VERIFIED, AssignmentStatus::LOCKED], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function baris(AssessmentPeriodAssignment $a, ?int $siswaKosong = null, ?int $siswaTotal = null): array
    {
        return [
            'assignment_id' => (int) $a->getKey(),
            'mapel' => (string) $a->subject_name_snapshot,
            'guru' => (string) $a->teacher_name_snapshot,
            'rombel' => (string) $a->rombel_name_snapshot,
            'status' => (string) ($a->status instanceof AssignmentStatus ? $a->status->value : $a->status),
            'siswa_kosong' => $siswaKosong,
            'siswa_total' => $siswaTotal,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $belumDiisi
     * @param  array<int, array<string, mixed>>  $belumVerifikasi
     * @return array<string, mixed>
     */
    private function hasil(int $totalMapel, array $belumDiisi, array $belumVerifikasi): array
    {
        $sementara = $belumDiisi !== [];

        $ringkasan = match (true) {
            $totalMapel === 0 => 'Belum ada mapel pada kelas ini.',
            $sementara => sprintf(
                'Rapor SEMENTARA — %d dari %d mapel belum ada nilainya.',
                count($belumDiisi),
                $totalMapel,
            ),
            $belumVerifikasi !== [] => sprintf(
                'Nilai lengkap, %d mapel menunggu verifikasi.',
                count($belumVerifikasi),
            ),
            default => 'Nilai lengkap dan terverifikasi. Rapor FINAL.',
        };

        return [
            'sementara' => $sementara,
            'total_mapel' => $totalMapel,
            'mapel_belum_diisi' => $belumDiisi,
            'mapel_belum_verifikasi' => $belumVerifikasi,
            'jumlah_belum_diisi' => count($belumDiisi),
            'jumlah_belum_verifikasi' => count($belumVerifikasi),
            'ringkasan' => $ringkasan,
        ];
    }
}
