<?php

namespace App\Support\Uks;

use App\Models\DataSiswa;
use App\Models\UksRecord;
use App\Support\Admin\Dashboard\DashboardCacheSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class UksAnthropometrySupport
{
    protected static ?bool $hasStudentColumn = null;

    protected static ?bool $hasAdminIdColumn = null;

    public static function hasStudentColumn(): bool
    {
        return static::$hasStudentColumn ??= Schema::hasTable('uks_records')
            && Schema::hasColumn('uks_records', 'siswa_id');
    }

    public static function hasAdminIdColumn(): bool
    {
        return static::$hasAdminIdColumn ??= Schema::hasTable('uks_records')
            && Schema::hasColumn('uks_records', 'admin_id');
    }

    public static function activeStudentsQuery(mixed $user): Builder
    {
        return DataSiswa::query()
            ->visibleToUser($user)
            ->where('status', 'aktif')
            ->select('data_siswa.*')
            ->selectSub(static::latestMeasurementValueSubquery('berat_badan'), 'latest_berat_badan')
            ->selectSub(static::latestMeasurementValueSubquery('tinggi_badan'), 'latest_tinggi_badan')
            ->selectSub(static::latestMeasurementValueSubquery('lingkar_kepala'), 'latest_lingkar_kepala')
            ->selectSub(static::latestMeasurementDateSubquery(), 'latest_measurement_date')
            ->selectSub(static::latestMeasurementNoteSubquery(), 'latest_measurement_note');
    }

    public static function activeStudentsCount(mixed $user): int
    {
        return DashboardCacheSupport::remember(
            'uks',
            'anthropometry-active-students:'.static::scopeKey($user),
            fn (): int => DataSiswa::query()
                ->visibleToUser($user)
                ->where('status', 'aktif')
                ->count(),
        );
    }

    public static function unmeasuredThisMonthCount(mixed $user): int
    {
        return DashboardCacheSupport::remember(
            'uks',
            'anthropometry-unmeasured-month:'.static::scopeKey($user),
            function () use ($user): int {
                $monthStart = now()->startOfMonth()->toDateString();
                $nextMonthStart = now()->startOfMonth()->addMonth()->toDateString();

                return DataSiswa::query()
                    ->visibleToUser($user)
                    ->where('status', 'aktif')
                    ->whereNotExists(function ($subQuery) use ($monthStart, $nextMonthStart): void {
                        $subQuery
                            ->selectRaw('1')
                            ->from('uks_records')
                            ->where(function ($query): void {
                                if (static::hasStudentColumn()) {
                                    $query
                                        ->whereColumn('uks_records.siswa_id', 'data_siswa.id')
                                        ->orWhere(function ($legacyQuery): void {
                                            $legacyQuery
                                                ->whereNull('uks_records.siswa_id')
                                                ->whereColumn('uks_records.nama_siswa', 'data_siswa.nama');
                                        });

                                    return;
                                }

                                $query->whereColumn('uks_records.nama_siswa', 'data_siswa.nama');
                            })
                            ->whereDate('uks_records.tanggal_sakit', '>=', $monthStart)
                            ->whereDate('uks_records.tanggal_sakit', '<', $nextMonthStart)
                            ->where(function ($query): void {
                                $query
                                    ->whereNotNull('uks_records.berat_badan')
                                    ->orWhereNotNull('uks_records.tinggi_badan')
                                    ->orWhereNotNull('uks_records.lingkar_kepala');
                            });
                    })
                    ->count();
            },
        );
    }

    protected static function scopeKey(mixed $user): string
    {
        if (! is_object($user)) {
            return 'guest';
        }

        return sha1(json_encode([
            'id' => method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : null,
            'boarding_angkatan_scope' => $user->boarding_angkatan_scope ?? null,
            'boarding_rombel_scope' => $user->boarding_rombel_scope ?? null,
            'guru_tendik_id' => $user->guru_tendik_id ?? null,
            'guru_walas_scope' => $user->guru_walas_scope ?? null,
        ]));
    }

    public static function resolveStudentId(?string $studentName, ?string $classroom): ?int
    {
        if (! filled($studentName)) {
            return null;
        }

        $baseQuery = DataSiswa::query()->where('nama', trim((string) $studentName));

        if (filled($classroom)) {
            $matchByClass = (clone $baseQuery)
                ->where('rombel_saat_ini', trim((string) $classroom))
                ->value('id');

            if ($matchByClass !== null) {
                return (int) $matchByClass;
            }
        }

        $matchByName = (clone $baseQuery)
            ->orderByRaw("CASE WHEN status = 'aktif' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->value('id');

        return $matchByName !== null ? (int) $matchByName : null;
    }

    protected static function latestMeasurementValueSubquery(string $column): Builder
    {
        return static::baseMeasurementQuery()
            ->whereNotNull($column)
            ->select($column);
    }

    protected static function latestMeasurementDateSubquery(): Builder
    {
        return static::baseMeasurementQuery()
            ->where(function ($query): void {
                $query
                    ->whereNotNull('berat_badan')
                    ->orWhereNotNull('tinggi_badan')
                    ->orWhereNotNull('lingkar_kepala');
            })
            ->select('tanggal_sakit');
    }

    protected static function latestMeasurementNoteSubquery(): Builder
    {
        return static::baseMeasurementQuery()
            ->where(function ($query): void {
                $query
                    ->whereNotNull('berat_badan')
                    ->orWhereNotNull('tinggi_badan')
                    ->orWhereNotNull('lingkar_kepala');
            })
            ->select('catatan');
    }

    protected static function baseMeasurementQuery(): Builder
    {
        return UksRecord::query()
            ->where(function ($query): void {
                if (static::hasStudentColumn()) {
                    $query
                        ->whereColumn('uks_records.siswa_id', 'data_siswa.id')
                        ->orWhere(function ($legacyQuery): void {
                            $legacyQuery
                                ->whereNull('uks_records.siswa_id')
                                ->whereColumn('uks_records.nama_siswa', 'data_siswa.nama');
                        });

                    return;
                }

                $query->whereColumn('uks_records.nama_siswa', 'data_siswa.nama');
            })
            ->orderByRaw('CASE WHEN tanggal_sakit IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('tanggal_sakit')
            ->orderByDesc('id')
            ->limit(1);
    }
}
