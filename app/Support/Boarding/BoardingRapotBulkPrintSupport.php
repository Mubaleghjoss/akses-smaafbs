<?php

namespace App\Support\Boarding;

use App\Models\BoardingRapot;
use App\Models\DataSiswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class BoardingRapotBulkPrintSupport
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(
        User $user,
        ?string $periodeTahun = null,
        ?string $semester = null,
        ?string $rombel = null,
        ?string $jenisKelamin = 'all',
    ): array {
        $periodeTahun = filled($periodeTahun) ? $periodeTahun : BoardingRapot::defaultPeriodeTahun();
        $semester = filled($semester) ? $semester : BoardingRapot::defaultSemester();
        $jenisKelamin = $jenisKelamin ?: 'all';

        $totalStudents = static::studentQuery($user, $rombel, $jenisKelamin)->count();
        $totalRapots = static::rapotQuery($user, $periodeTahun, $semester, $rombel, $jenisKelamin)->count();
        $readyRapots = static::rapotQuery($user, $periodeTahun, $semester, $rombel, $jenisKelamin)
            ->where('status_rapot', BoardingRapot::STATUS_READY_PRINT)
            ->count();
        $notReadyRapots = max(0, $totalRapots - $readyRapots);
        $missingRapots = max(0, $totalStudents - $totalRapots);

        return [
            'periode_tahun' => $periodeTahun,
            'semester' => $semester,
            'rombel' => filled($rombel) ? $rombel : null,
            'jenis_kelamin' => $jenisKelamin,
            'scope_label' => static::scopeLabel($user, $rombel, $jenisKelamin),
            'total_students' => $totalStudents,
            'total_rapots' => $totalRapots,
            'ready_rapots' => $readyRapots,
            'not_ready_rapots' => $notReadyRapots,
            'missing_rapots' => $missingRapots,
            'is_complete' => $totalStudents > 0
                && $readyRapots >= $totalStudents
                && $notReadyRapots === 0
                && $missingRapots === 0,
        ];
    }

    public static function rapotQuery(
        User $user,
        ?string $periodeTahun = null,
        ?string $semester = null,
        ?string $rombel = null,
        ?string $jenisKelamin = 'all',
        ?array $columns = null,
    ): Builder {
        $query = BoardingRapot::query()
            ->forDocument($user)
            ->whereHas('siswa', function (Builder $query) use ($user, $rombel, $jenisKelamin): void {
                static::applyStudentScope($query, $user, $rombel, $jenisKelamin);
            })
            ->when(filled($periodeTahun), fn (Builder $query): Builder => $query->where('periode_tahun', $periodeTahun))
            ->when(filled($semester), fn (Builder $query): Builder => $query->where('semester', $semester));

        if ($columns !== null) {
            $query->select($columns);
        }

        return $query;
    }

    public static function studentQuery(User $user, ?string $rombel = null, ?string $jenisKelamin = 'all'): Builder
    {
        return static::applyStudentScope(DataSiswa::query(), $user, $rombel, $jenisKelamin);
    }

    /**
     * @return array<int, string>
     */
    public static function documentColumns(): array
    {
        $columns = [
            'id',
            'siswa_id',
            'pamong_user_id',
            'periode_tahun',
            'semester',
            'nomor_dokumen',
            'predikat_boarding',
            'status_rapot',
            'tanggal_rapot',
            'generated_at',
            'rekap_payload',
            'ringkasan_pencapaian',
            'catatan_pamong',
            'rekomendasi_tindak_lanjut',
            'wali_pamong_nama',
            'kepala_boarding_nama',
            'mudir_asrama_nama',
            'tempat_cetak',
        ];

        foreach (['administrasi_rapot_items', 'kelas_boarding_override'] as $column) {
            if (SchemaFacade::hasColumn('boarding_rapots', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public static function incompleteConfirmationText(array $summary): string
    {
        $ready = number_format((int) ($summary['ready_rapots'] ?? 0), 0, ',', '.');
        $total = number_format((int) ($summary['total_students'] ?? 0), 0, ',', '.');
        $notReady = (int) ($summary['not_ready_rapots'] ?? 0);
        $missing = (int) ($summary['missing_rapots'] ?? 0);
        $details = collect([
            $notReady > 0 ? number_format($notReady, 0, ',', '.').' rapot belum berstatus Siap Cetak' : null,
            $missing > 0 ? number_format($missing, 0, ',', '.').' murid belum punya rapot pada periode ini' : null,
        ])->filter()->implode(', ');

        $suffix = $details !== '' ? ' Detail: '.$details.'.' : '';

        return "Baru {$ready} dari {$total} murid dalam {$summary['scope_label']} yang siap cetak.{$suffix} Apakah yakin ingin mencetak rapot yang sudah siap?";
    }

    protected static function applyStudentScope(Builder $query, User $user, ?string $rombel, ?string $jenisKelamin): Builder
    {
        DataSiswa::applyVisibleScope($query, $user);

        if (SchemaFacade::hasColumn('data_siswa', 'status')) {
            $query->where('status', 'aktif');
        }

        if (filled($rombel)) {
            $query->where('rombel_saat_ini', $rombel);
        }

        if ($user->hasFullAdminAccess() && in_array($jenisKelamin, ['L', 'P'], true)) {
            $query->where('jk', $jenisKelamin);
        }

        return $query;
    }

    protected static function scopeLabel(User $user, ?string $rombel, ?string $jenisKelamin): string
    {
        $parts = [];

        if ($user->isBoardingPamong() && ! $user->hasFullAdminAccess()) {
            $parts[] = 'scope pamong';
        } else {
            $parts[] = 'filter yang dipilih';
        }

        if (filled($rombel)) {
            $parts[] = 'kelas '.$rombel;
        }

        if ($user->hasFullAdminAccess() && in_array($jenisKelamin, ['L', 'P'], true)) {
            $parts[] = $jenisKelamin === 'L' ? 'putra' : 'putri';
        }

        return implode(' ', $parts);
    }
}
