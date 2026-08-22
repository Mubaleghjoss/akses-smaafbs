<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class BoardingMateriProgress extends Model
{
    public const GROUP_OPTIONS = [
        'pengetesan_makna' => '4. Materi Pengetesan Makna',
        'catatan_saran' => 'Catatan dan Saran',
    ];

    public const GRADE_OPTIONS = [
        'baik' => 'Baik',
        'cukup' => 'Cukup',
        'kurang' => 'Kurang',
    ];

    public const HAFALAN_MATERI_LABELS = [
        'pegon_bacaan' => 'Materi Hafalan Kelas Pegon Bacaan',
        'lambatan' => 'Materi Hafalan Kelas Lambatan',
        'cepatan' => 'Materi Hafalan Kelas Cepatan',
        'materi_tambahan_hafalan' => 'Materi Hafalan Kelas Materi Tambahan',
    ];

    protected $table = 'boarding_materi_progresses';

    protected $guarded = [];

    protected $casts = [
        'boarding_pencapaian_id' => 'integer',
        'urutan' => 'integer',
        'updated_by_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $progress): void {
            if (auth()->id()) {
                $progress->updated_by_user_id = auth()->id();
            }
        });

        static::saved(function (self $progress): void {
            $progress->pencapaian?->syncLatestBoardingModuleDate();
        });

        static::deleted(function (self $progress): void {
            $progress->pencapaian?->syncLatestBoardingModuleDate();
        });
    }

    public static function groupOptions(): array
    {
        return self::GROUP_OPTIONS;
    }

    public static function groupLabel(?string $group): string
    {
        return self::GROUP_OPTIONS[$group ?? ''] ?? ($group ?: 'Materi Boarding');
    }

    public static function gradeOptions(): array
    {
        return self::GRADE_OPTIONS;
    }

    public static function gradeLabel(?string $grade): string
    {
        return self::GRADE_OPTIONS[$grade ?? ''] ?? 'Belum Diisi';
    }

    public static function gradeColor(?string $grade): string
    {
        return match ($grade) {
            'baik' => 'success',
            'cukup' => 'warning',
            'kurang' => 'danger',
            default => 'gray',
        };
    }

    public function isFilled(): bool
    {
        return filled($this->updated_by_user_id)
            || filled($this->grade)
            || filled($this->notes);
    }

    public function scopeFilled(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->whereNotNull('updated_by_user_id')
                ->orWhereNotNull('grade')
                ->orWhere(function (Builder $notesQuery): void {
                    $notesQuery
                        ->whereNotNull('notes')
                        ->where('notes', '!=', '');
                });
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaultTargets(): array
    {
        return [
            ['target_key' => 'pengetesan_makna', 'target_group' => 'pengetesan_makna', 'target_name' => 'Pengetesan Makna', 'urutan' => 1],
            ['target_key' => 'kedisiplinan', 'target_group' => 'catatan_saran', 'target_name' => 'Kedisiplinan', 'urutan' => 10],
            ['target_key' => 'ketertiban', 'target_group' => 'catatan_saran', 'target_name' => 'Ketertiban', 'urutan' => 11],
            ['target_key' => 'akhlak', 'target_group' => 'catatan_saran', 'target_name' => 'Akhlak', 'urutan' => 12],
            ['target_key' => 'kesemangatan', 'target_group' => 'catatan_saran', 'target_name' => 'Kesemangatan', 'urutan' => 13],
        ];
    }

    public static function ensureDefaultsForPencapaian(BoardingPencapaian|int $pencapaian): void
    {
        $pencapaianId = $pencapaian instanceof BoardingPencapaian ? $pencapaian->getKey() : (int) $pencapaian;

        if ($pencapaianId <= 0) {
            return;
        }

        $existingKeys = self::query()
            ->where('boarding_pencapaian_id', $pencapaianId)
            ->pluck('target_key');

        $now = now();
        $missing = collect(self::defaultTargets())
            ->reject(fn (array $target): bool => $existingKeys->contains($target['target_key']))
            ->map(fn (array $target): array => [
                'boarding_pencapaian_id' => $pencapaianId,
                'target_key' => $target['target_key'],
                'target_group' => $target['target_group'],
                'target_name' => $target['target_name'],
                'urutan' => $target['urutan'],
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if ($missing !== []) {
            self::query()->insertOrIgnore($missing);
        }
    }

    public static function hafalanSummaries(BoardingPencapaian|int $pencapaian): array
    {
        $pencapaianId = $pencapaian instanceof BoardingPencapaian ? $pencapaian->getKey() : (int) $pencapaian;
        $materiKeys = array_keys(self::HAFALAN_MATERI_LABELS);

        $totals = BoardingHafalanPoint::query()
            ->where('is_active', true)
            ->whereIn('jenis', BoardingHafalanPoint::hafalanJenis())
            ->whereIn('materi_key', $materiKeys)
            ->selectRaw('materi_key, count(*) as aggregate')
            ->groupBy('materi_key')
            ->pluck('aggregate', 'materi_key');

        $assessments = BoardingHafalanAssessment::query()
            ->join('boarding_hafalan_points', 'boarding_hafalan_points.id', '=', 'boarding_hafalan_assessments.boarding_hafalan_point_id')
            ->where('boarding_hafalan_assessments.boarding_pencapaian_id', $pencapaianId)
            ->where('boarding_hafalan_points.is_active', true)
            ->whereIn('boarding_hafalan_points.jenis', BoardingHafalanPoint::hafalanJenis())
            ->whereIn('boarding_hafalan_points.materi_key', $materiKeys)
            ->selectRaw('boarding_hafalan_points.materi_key, count(*) as assessed_count, avg(boarding_hafalan_assessments.score) as average_score')
            ->groupBy('boarding_hafalan_points.materi_key')
            ->get()
            ->keyBy('materi_key');

        return collect(self::HAFALAN_MATERI_LABELS)
            ->map(function (string $label, string $materiKey) use ($totals, $assessments): array {
                $row = $assessments->get($materiKey);
                $total = (int) ($totals[$materiKey] ?? 0);
                $assessed = (int) ($row?->assessed_count ?? 0);
                $average = $row?->average_score !== null ? round((float) $row->average_score, 1) : null;
                $grade = self::hafalanGrade($total, $assessed, $average);

                return [
                    'materi_key' => $materiKey,
                    'judul' => $label,
                    'total' => $total,
                    'assessed' => $assessed,
                    'average' => $average,
                    'grade' => $grade,
                    'grade_label' => $grade ? self::gradeLabel($grade) : ($total > 0 ? 'Belum Lengkap' : 'Belum Ada Materi'),
                    'color' => $grade ? self::gradeColor($grade) : 'gray',
                ];
            })
            ->values()
            ->all();
    }

    public static function hafalanGrade(int $total, int $assessed, ?float $average): ?string
    {
        if ($total <= 0 || $assessed < $total || $average === null) {
            return null;
        }

        return match (true) {
            $average > 85 => 'baik',
            $average < 60 => 'kurang',
            default => 'cukup',
        };
    }

    public static function maknaGroupSummary(BoardingPencapaian|int $pencapaian, string $group): array
    {
        $pencapaianId = $pencapaian instanceof BoardingPencapaian ? $pencapaian->getKey() : (int) $pencapaian;

        BoardingMaknaProgress::ensureDefaultsForPencapaian($pencapaianId);

        /** @var Collection<int, BoardingMaknaProgress> $rows */
        $rows = BoardingMaknaProgress::query()
            ->where('boarding_pencapaian_id', $pencapaianId)
            ->where('target_group', $group)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        $khatam = $rows->where('status', 'khatam')->count();
        $sebagian = $rows->where('status', 'sebagian')->count();
        $total = $rows->count();
        $blank = max($total - $khatam - $sebagian, 0);

        return [
            'group' => $group,
            'judul' => BoardingMaknaProgress::groupLabel($group),
            'total' => $total,
            'khatam' => $khatam,
            'sebagian' => $sebagian,
            'blank' => $blank,
            'summary_label' => "Khatam {$khatam}, sebagian {$sebagian}, belum diisi {$blank} dari {$total} target",
        ];
    }

    public static function bacaanSummary(BoardingPencapaian|int $pencapaian): array
    {
        $pencapaianId = $pencapaian instanceof BoardingPencapaian ? $pencapaian->getKey() : (int) $pencapaian;
        $query = BoardingBacaanAssessment::query()
            ->where('boarding_pencapaian_id', $pencapaianId);
        $count = (clone $query)->count();
        $latest = (clone $query)
            ->with('reviewerUser:id,name')
            ->orderByDesc('assessed_at')
            ->orderByDesc('id')
            ->first();

        return [
            'total_sessions' => $count,
            'summary_label' => $count > 0
                ? "{$count} simakan, terakhir ".($latest?->assessed_at ? Carbon::parse($latest->assessed_at)->translatedFormat('d M Y') : '-')
                : 'Belum ada riwayat bacaan',
            'class_label' => BoardingBacaanAssessment::classLabel($latest?->kelas_bacaan),
            'latest_grades' => $latest
                ? 'PP '.$latest->pp_grade.' | KL '.$latest->kl_grade.' | TJ '.$latest->tj_grade.' | MJ '.$latest->mj_grade
                : '-',
            'latest_reviewer' => $latest?->reviewerUser?->name ?: ($latest?->reviewer_name ?: '-'),
        ];
    }

    public function pencapaian(): BelongsTo
    {
        return $this->belongsTo(BoardingPencapaian::class, 'boarding_pencapaian_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
