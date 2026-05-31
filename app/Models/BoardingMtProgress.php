<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingMtProgress extends Model
{
    public const GROUP_OPTIONS = [
        'makna_hadits' => 'Materi Makna Hadits',
        'materi_tambahan' => 'Materi Tambahan',
        'hafalan' => 'Materi Hafalan',
        'catatan_saran' => 'Catatan dan Saran',
    ];

    public const GRADE_OPTIONS = [
        'baik' => 'Baik',
        'cukup' => 'Cukup',
        'kurang' => 'Kurang',
    ];

    public const GRADE_OPTIONS_BAIK_CUKUP = [
        'baik' => 'Baik',
        'cukup' => 'Cukup',
    ];

    protected $table = 'boarding_mt_progresses';

    protected $guarded = [];

    protected $casts = [
        'boarding_pencapaian_id' => 'integer',
        'progress_value' => 'integer',
        'target_total' => 'integer',
        'urutan' => 'integer',
        'updated_by_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $progress): void {
            if ($progress->input_type === 'progress') {
                $progress->grade = null;
            } else {
                $progress->progress_value = null;
                $progress->target_total = null;
            }

            if (auth()->id()) {
                $progress->updated_by_user_id = auth()->id();
            }
        });

        static::saved(function (self $progress): void {
            $progress->pencapaian?->syncLatestBoardingModuleDate();
        });
    }

    public static function groupOptions(): array
    {
        return self::GROUP_OPTIONS;
    }

    public static function groupLabel(?string $group): string
    {
        return self::GROUP_OPTIONS[$group ?? ''] ?? ($group ?: 'Materi MT');
    }

    public static function gradeLabel(?string $grade): string
    {
        return self::GRADE_OPTIONS[$grade ?? ''] ?? 'Belum Diisi';
    }

    public function gradeOptions(): array
    {
        return $this->grade_scale === 'baik_cukup'
            ? self::GRADE_OPTIONS_BAIK_CUKUP
            : self::GRADE_OPTIONS;
    }

    public function progressSummary(): string
    {
        if ($this->input_type === 'progress') {
            $unit = $this->unit_label ?: 'item';
            $progress = (int) ($this->progress_value ?? 0);
            $target = (int) ($this->target_total ?? 0);

            return "{$progress} / {$target} {$unit}";
        }

        return self::gradeLabel($this->grade);
    }

    public function isFilled(): bool
    {
        return filled($this->updated_by_user_id)
            || $this->progress_value !== null
            || filled($this->grade)
            || filled($this->notes);
    }

    public function scopeFilled(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->whereNotNull('updated_by_user_id')
                ->orWhereNotNull('progress_value')
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
            ['target_key' => 'muslim_jilid_1', 'target_group' => 'makna_hadits', 'target_name' => 'Muslim Jilid 1', 'input_type' => 'progress', 'unit_label' => 'lembar', 'urutan' => 1],
            ['target_key' => 'muslim_jilid_2', 'target_group' => 'makna_hadits', 'target_name' => 'Muslim Jilid 2', 'input_type' => 'progress', 'unit_label' => 'lembar', 'urutan' => 2],
            ['target_key' => 'muslim_jilid_3', 'target_group' => 'makna_hadits', 'target_name' => 'Muslim Jilid 3', 'input_type' => 'progress', 'unit_label' => 'lembar', 'urutan' => 3],
            ['target_key' => 'muslim_jilid_4', 'target_group' => 'makna_hadits', 'target_name' => 'Muslim Jilid 4', 'input_type' => 'progress', 'unit_label' => 'lembar', 'urutan' => 4],
            ['target_key' => 'tugas_praktek', 'target_group' => 'materi_tambahan', 'target_name' => 'Tugas Praktek', 'input_type' => 'grade', 'grade_scale' => 'baik_cukup', 'urutan' => 10],
            ['target_key' => 'hafalan_surat_quran_juz_1', 'target_group' => 'hafalan', 'target_name' => 'Hafalan Surat Quran Juz 1', 'input_type' => 'progress', 'unit_label' => 'halaman', 'urutan' => 20],
            ['target_key' => 'hafalan_dalil_29_karakter_luhur', 'target_group' => 'hafalan', 'target_name' => 'Hafalan Dalil 29 Karakter Luhur', 'input_type' => 'progress', 'unit_label' => 'dalil', 'target_total' => 29, 'urutan' => 21],
            ['target_key' => 'kedisiplinan', 'target_group' => 'catatan_saran', 'target_name' => 'Kedisiplinan', 'input_type' => 'grade', 'grade_scale' => 'baik_cukup_kurang', 'urutan' => 30],
            ['target_key' => 'ketertiban', 'target_group' => 'catatan_saran', 'target_name' => 'Ketertiban', 'input_type' => 'grade', 'grade_scale' => 'baik_cukup_kurang', 'urutan' => 31],
            ['target_key' => 'akhlak', 'target_group' => 'catatan_saran', 'target_name' => 'Akhlak', 'input_type' => 'grade', 'grade_scale' => 'baik_cukup_kurang', 'urutan' => 32],
            ['target_key' => 'kesemangatan', 'target_group' => 'catatan_saran', 'target_name' => 'Kesemangatan', 'input_type' => 'grade', 'grade_scale' => 'baik_cukup_kurang', 'urutan' => 33],
        ];
    }

    public static function defaultTargetCount(): int
    {
        return count(self::defaultTargets());
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
                'input_type' => $target['input_type'],
                'grade_scale' => $target['grade_scale'] ?? null,
                'target_total' => $target['target_total'] ?? null,
                'unit_label' => $target['unit_label'] ?? null,
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

    public function pencapaian(): BelongsTo
    {
        return $this->belongsTo(BoardingPencapaian::class, 'boarding_pencapaian_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
