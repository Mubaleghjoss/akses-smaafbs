<?php

namespace App\Support\Assessment;

use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cakupan data penilaian untuk satu pengguna pada satu periode.
 *
 * Aturan (menyatukan logika yang sebelumnya diduplikasi di dashboard, type hub,
 * dan halaman status pengiriman):
 *   1. Admin / pemegang izin verifikasi / kepala sekolah  -> seluruh data.
 *   2. Bukan guru (tanpa guru_tendik_id)                  -> tidak ada data.
 *   3. Guru -> GABUNGAN penugasan miliknya sendiri DAN penugasan kelas yang
 *      ia ampu sebagai wali kelas.
 *
 * Butir 3 memakai OR, bukan menggantikan. Wali kelas yang juga mengajar mapel
 * di kelas lain tetap harus melihat penugasan mapelnya sendiri; bila hanya
 * kelas wali yang diambil, penugasan mapel di kelas lain hilang dari daftar.
 */
final class AssessmentStatusScope
{
    /**
     * @param  bool  $butuhIzinWaliKelas  Bila true, kelas wali hanya disertakan
     *         saat pengguna memegang izin 'penilaian.homeroom'. Dipakai dashboard
     *         yang memang menerapkan syarat izin tersebut.
     */
    public function apply(
        Builder $query,
        User $user,
        int $periodId,
        bool $butuhIzinWaliKelas = false,
    ): Builder {
        if ($this->canViewAll($user)) {
            return $query;
        }

        if (! $user->guru_tendik_id) {
            return $query->whereRaw('1 = 0');
        }

        $homeroomRombelIds = $this->homeroomRombelIds($user, $periodId, $butuhIzinWaliKelas);

        return $query->where(function (Builder $assignments) use ($user, $homeroomRombelIds): void {
            $assignments->where('teacher_id', $user->guru_tendik_id);

            if ($homeroomRombelIds !== []) {
                $assignments->orWhereIn('assessment_period_rombel_id', $homeroomRombelIds);
            }
        });
    }

    public function mode(User $user, int $periodId, bool $butuhIzinWaliKelas = false): string
    {
        if ($this->canViewAll($user)) {
            return 'all';
        }

        if (! $user->guru_tendik_id) {
            return 'none';
        }

        return $this->homeroomRombelIds($user, $periodId, $butuhIzinWaliKelas) !== []
            ? 'homeroom'
            : 'teacher';
    }

    /**
     * @return array<int, int>
     */
    public function homeroomRombelIds(
        User $user,
        int $periodId,
        bool $butuhIzinWaliKelas = false,
    ): array {
        if (! $user->guru_tendik_id || $periodId <= 0) {
            return [];
        }

        if ($butuhIzinWaliKelas && ! $user->can('penilaian.homeroom')) {
            return [];
        }

        return AssessmentPeriodHomeroom::query()
            ->where('assessment_period_id', $periodId)
            ->where('teacher_id', $user->guru_tendik_id)
            ->orderBy('assessment_period_rombel_id')
            ->pluck('assessment_period_rombel_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function canViewAll(User $user): bool
    {
        return $user->hasFullAdminAccess()
            || $user->can('penilaian.verify')
            || $user->hasRole('kepala_sekolah');
    }

    /**
     * Bolehkah pengguna mencetak rapor rombel tertentu pada satu periode?
     *
     * Kebijakan sekolah:
     *   - Admin / kurikulum / kepala sekolah  -> seluruh rombel.
     *   - Wali kelas                          -> HANYA rombel yang ia ampu.
     *   - Guru mapel                          -> tidak boleh mencetak.
     *
     * Pembatasan "hanya kelas sendiri" tidak dapat diwakili oleh izin, karena
     * izin tidak mengenal rombel. Karena itu diperiksa di sini — satu tempat —
     * bukan disebar di view atau di setiap halaman rapor.
     */
    public function bolehCetakRapor(User $user, int $periodId, int $rombelId): bool
    {
        if (! $user->can('penilaian.report.generate')) {
            return false;
        }

        // Pemegang akses menyeluruh tidak dibatasi rombel.
        if ($this->canViewAll($user)) {
            return true;
        }

        // Sisanya (wali kelas) hanya rombel yang benar-benar ia ampu pada
        // periode ini — dibuktikan baris assessment_period_homerooms, bukan
        // label peran pada akun.
        return in_array($rombelId, $this->homeroomRombelIds($user, $periodId), true);
    }

    /**
     * Daftar rombel yang boleh dicetak pengguna pada satu periode.
     * Mengembalikan null bila tidak dibatasi (seluruh rombel).
     *
     * @return array<int, int>|null
     */
    public function rombelBolehCetak(User $user, int $periodId): ?array
    {
        if (! $user->can('penilaian.report.generate')) {
            return [];
        }

        return $this->canViewAll($user)
            ? null
            : $this->homeroomRombelIds($user, $periodId);
    }
}
