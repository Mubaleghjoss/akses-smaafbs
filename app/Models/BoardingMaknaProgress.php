<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BoardingMaknaProgress extends Model
{
    public const STATUS_OPTIONS = [
        'belum_diisi' => 'Belum Diisi',
        'sebagian' => 'Sebagian',
        'khatam' => 'Khatam',
    ];

    public const GROUP_OPTIONS = [
        'quran' => "Materi Qur'an : Makna Qur'an",
        'hadits_materi' => 'Makna Hadits',
    ];

    protected $table = 'boarding_makna_progresses';

    protected $guarded = [];

    protected $casts = [
        'boarding_pencapaian_id' => 'integer',
        'urutan' => 'integer',
        'remaining_pages' => 'integer',
        'total_pages' => 'integer',
        'updated_by_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $progress): void {
            if ($progress->status !== 'sebagian') {
                $progress->remaining_pages = null;
                $progress->total_pages = null;
            }

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

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public static function groupOptions(): array
    {
        return self::GROUP_OPTIONS;
    }

    public static function statusLabel(?string $status): string
    {
        return self::statusOptions()[$status ?? 'belum_diisi'] ?? ($status ?: 'Belum Diisi');
    }

    public static function groupLabel(?string $group): string
    {
        return self::groupOptions()[$group ?? 'quran'] ?? ($group ?: 'Makna');
    }

    /**
     * @return array<int, array{target_key: string, target_group: string, target_name: string, urutan: int}>
     */
    public static function defaultTargets(): array
    {
        $targets = [];

        foreach (range(1, 30) as $juz) {
            $targets[] = [
                'target_key' => 'quran_juz_'.$juz,
                'target_group' => 'quran',
                'target_name' => "Makna Qur'an Juz ".$juz,
                'urutan' => $juz,
            ];
        }

        $haditsAndMaterials = [
            'K. Sholah',
            'K. Nawafil',
            'K. Da\'wat',
            'K. Adab',
            'K. Jannah Wannar',
            'K. Janaiz',
            'K. Adillah',
            'K. Shoum',
            'K. Ahkam',
            'K. Manasik Waljihad',
            'K. Jihad',
            'K. Haji',
            'K. Manasikil Haji',
            'K. Imaroh',
            'Kanzil Umal',
            'K. Faroid',
            'K. Khotbah',
            'Materi Tata Krama',
            'Materi Bacaan',
            'Materi Pegon',
            'Materi Lambatan',
            'Materi Cepatan',
            'Materi Saringan',
            'K. Nikah',
            'K. Talaq',
            'K. Zakat',
        ];

        foreach ($haditsAndMaterials as $index => $label) {
            $targets[] = [
                'target_key' => 'hadits_materi_'.Str::slug($label, '_'),
                'target_group' => 'hadits_materi',
                'target_name' => $label,
                'urutan' => 100 + $index,
            ];
        }

        return static::appendMasterTargets($targets);
    }

    /**
     * @param  array<int, array{target_key: string, target_group: string, target_name: string, urutan: int}>  $targets
     * @return array<int, array{target_key: string, target_group: string, target_name: string, urutan: int}>
     */
    protected static function appendMasterTargets(array $targets): array
    {
        if (! Schema::hasTable('boarding_hafalan_points')) {
            return $targets;
        }

        $existingNames = collect($targets)
            ->groupBy('target_group')
            ->map(fn ($rows) => collect($rows)
                ->mapWithKeys(fn (array $target): array => [static::normalizeTargetName($target['target_name']) => true])
                ->all())
            ->all();

        $query = BoardingHafalanPoint::query()
            ->where('is_active', true)
            ->whereIn('materi_key', [
                BoardingHafalanPoint::MATERI_TAMBAHAN_MAKNA_QURAN_KEY,
                BoardingHafalanPoint::MATERI_TAMBAHAN_MAKNA_HADITS_KEY,
            ])
            ->whereIn('jenis', ['makna_quran', 'makna_hadits']);

        if (Schema::hasColumn('boarding_hafalan_points', 'materi_scope')) {
            $query->where('materi_scope', 'boarding');
        }

        $masterTargets = $query
            ->orderByRaw(BoardingHafalanPoint::materiOrderSql())
            ->orderBy('urutan')
            ->orderBy('id')
            ->get(['id', 'materi_key', 'jenis', 'nama_point', 'urutan']);

        foreach ($masterTargets as $point) {
            $group = $point->jenis === 'makna_quran' ? 'quran' : 'hadits_materi';
            $normalizedName = static::normalizeTargetName($point->nama_point);

            if ((bool) ($existingNames[$group][$normalizedName] ?? false)) {
                continue;
            }

            $targets[] = [
                'target_key' => ($group === 'quran' ? 'quran_master_' : 'hadits_materi_master_').$point->getKey(),
                'target_group' => $group,
                'target_name' => $point->nama_point,
                'urutan' => (int) $point->urutan,
            ];

            $existingNames[$group][$normalizedName] = true;
        }

        return collect($targets)
            ->sortBy(fn (array $target): string => sprintf(
                '%02d|%05d|%s',
                $target['target_group'] === 'quran' ? 1 : 2,
                (int) $target['urutan'],
                $target['target_key'],
            ))
            ->values()
            ->all();
    }

    protected static function normalizeTargetName(?string $name): string
    {
        $normalized = Str::of((string) $name)
            ->lower()
            ->squish()
            ->toString();

        return str_replace(
            ["makna al-qur'an", 'makna al-quran'],
            ["makna qur'an", 'makna quran'],
            $normalized,
        );
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
                'urutan' => $target['urutan'],
                'status' => 'belum_diisi',
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if ($missing === []) {
            return;
        }

        self::query()->insertOrIgnore($missing);
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
