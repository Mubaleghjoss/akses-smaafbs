<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Str;

class BoardingKeuanganTransaksi extends Model
{
    protected static ?bool $kategoriColumnAvailable = null;

    protected static ?bool $arusColumnAvailable = null;

    protected static ?bool $createdByColumnAvailable = null;

    protected static ?bool $updatedByColumnAvailable = null;

    protected static ?bool $historyTableAvailable = null;

    public const INCOMING_CATEGORY_SLUGS = [
        'kas_umum',
    ];

    public const LEGACY_TYPE_LABELS = [
        'titipan_uang_saku' => 'Titipan Uang Saku',
        'pemberian_uang_saku' => 'Pemberian Uang Saku',
        'setoran_kas' => 'Setoran Kas',
    ];

    public const LEGACY_TYPE_TO_CATEGORY_SLUG = [
        'titipan_uang_saku' => 'kas_umum',
        'pemberian_uang_saku' => 'kas_kamar',
        'setoran_kas' => 'isrun',
    ];

    public const SUMMARY_BUCKETS = [
        'titipan' => [
            'legacy_types' => ['titipan_uang_saku'],
            'category_slugs' => ['kas_umum'],
        ],
        'pemberian' => [
            'legacy_types' => ['pemberian_uang_saku'],
            'category_slugs' => ['kas_kamar'],
        ],
        'kas' => [
            'legacy_types' => ['setoran_kas'],
            'category_slugs' => ['qurban', 'isrun'],
        ],
    ];

    protected $table = 'boarding_keuangan_transaksis';

    protected $guarded = [];

    protected $casts = [
        'tanggal_transaksi' => 'date',
        'nominal' => 'integer',
        'periode_bulan' => 'integer',
        'periode_tahun' => 'integer',
        'boarding_keuangan_kategori_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (! static::kategoriColumnAvailable()) {
                unset($record->boarding_keuangan_kategori_id);
            }

            if (! static::arusColumnAvailable()) {
                unset($record->arus);
            }

            if (! static::createdByColumnAvailable()) {
                unset($record->created_by);
            }

            if (! static::updatedByColumnAvailable()) {
                unset($record->updated_by);
            }

            if (static::kategoriColumnAvailable() && blank($record->boarding_keuangan_kategori_id) && filled($record->jenis_transaksi)) {
                $legacySlug = self::LEGACY_TYPE_TO_CATEGORY_SLUG[$record->jenis_transaksi] ?? null;

                if ($legacySlug) {
                    $record->boarding_keuangan_kategori_id = BoardingKeuanganKategori::idBySlug($legacySlug);
                }
            }

            if (static::kategoriRelationAvailable() && filled($record->boarding_keuangan_kategori_id)) {
                $slug = $record->resolveKategoriSlug();

                if ($slug && (blank($record->jenis_transaksi) || Str::startsWith((string) $record->jenis_transaksi, 'kategori:'))) {
                    $record->jenis_transaksi = 'kategori:'.$slug;
                }
            }

            if (static::arusColumnAvailable()) {
                if ($record->isDirty('arus') && in_array($record->getAttribute('arus'), ['masuk', 'keluar'], true)) {
                    $record->arus = $record->getAttribute('arus');
                } else {
                    $record->arus = $record->inferArusFallback();
                }
            }
        });

        static::creating(function (self $record): void {
            if (auth()->check()) {
                if (static::createdByColumnAvailable()) {
                    $record->created_by = auth()->id();
                }

                if (static::updatedByColumnAvailable()) {
                    $record->updated_by = auth()->id();
                }
            }
        });

        static::updating(function (self $record): void {
            if (static::updatedByColumnAvailable() && auth()->check()) {
                $record->updated_by = auth()->id();
            }
        });

        static::created(function (self $record): void {
            $record->logHistory('dibuat');
        });

        static::updated(function (self $record): void {
            $changes = array_keys($record->getChanges());
            $changes = array_values(array_diff($changes, ['updated_at']));

            if ($changes !== []) {
                $record->logHistory('diperbarui');
            }
        });
    }

    public function keuanganSiswa(): BelongsTo
    {
        return $this->belongsTo(BoardingKeuanganSiswa::class, 'boarding_keuangan_siswa_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(BoardingKeuanganKategori::class, 'boarding_keuangan_kategori_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(BoardingKeuanganTransaksiHistory::class, 'boarding_keuangan_transaksi_id')->latest('created_at');
    }

    public function scopeForSummaryBucket(Builder $query, string $bucket): Builder
    {
        $config = self::SUMMARY_BUCKETS[$bucket] ?? null;

        if (! $config) {
            return $query->whereRaw('1 = 0');
        }

        $legacyTypes = $config['legacy_types'] ?? [];
        $categorySlugs = $config['category_slugs'] ?? [];

        return $query->where(function (Builder $inner) use ($legacyTypes, $categorySlugs): void {
            if ($legacyTypes !== []) {
                $inner->whereIn('jenis_transaksi', $legacyTypes);
            }

            if ($categorySlugs !== [] && static::kategoriRelationAvailable()) {
                $inner->orWhereHas('kategori', fn (Builder $kategoriQuery) => $kategoriQuery->whereIn('slug', $categorySlugs));
            }
        });
    }

    public function scopeForCategorySlug(Builder $query, string $slug): Builder
    {
        $normalizedSlug = str_replace('-', '_', Str::lower(trim($slug)));
        $legacyTypes = self::legacyTypesForCategorySlug($normalizedSlug);
        $kategoriRelationAvailable = static::kategoriRelationAvailable();

        return $query->where(function (Builder $inner) use ($normalizedSlug, $legacyTypes, $kategoriRelationAvailable): void {
            if ($kategoriRelationAvailable) {
                $inner->whereHas('kategori', fn (Builder $kategoriQuery) => $kategoriQuery->where('slug', $normalizedSlug));
            }

            $inner
                ->orWhere('jenis_transaksi', 'kategori:'.$normalizedSlug)
                ->orWhere('jenis_transaksi', $normalizedSlug);

            if ($legacyTypes !== []) {
                $inner->orWhereIn('jenis_transaksi', $legacyTypes);
            }
        });
    }

    public static function legacyTypesForCategorySlug(string $slug): array
    {
        $legacyBySlug = [];

        foreach (self::LEGACY_TYPE_TO_CATEGORY_SLUG as $legacyType => $mappedSlug) {
            $legacyBySlug[$mappedSlug][] = $legacyType;
        }

        return $legacyBySlug[$slug] ?? [];
    }

    public function getKategoriLabelAttribute(): string
    {
        if (static::kategoriRelationAvailable() && filled($this->kategori?->nama)) {
            return Str::title((string) $this->kategori->nama);
        }

        if (filled($this->jenis_transaksi)) {
            if (array_key_exists($this->jenis_transaksi, self::LEGACY_TYPE_LABELS)) {
                return self::LEGACY_TYPE_LABELS[$this->jenis_transaksi].' (Legacy)';
            }

            if (Str::startsWith((string) $this->jenis_transaksi, 'kategori:')) {
                $slug = Str::after((string) $this->jenis_transaksi, 'kategori:');

                return Str::title(str_replace('_', ' ', $slug));
            }

            return (string) $this->jenis_transaksi;
        }

        return '-';
    }

    public function isUangMasuk(): bool
    {
        if (static::arusColumnAvailable() && filled($this->arus)) {
            return $this->arus === 'masuk';
        }

        return $this->inferArusFallback() === 'masuk';
    }

    public function logHistory(string $aksi): void
    {
        if (! static::historyTableAvailable()) {
            return;
        }

        $this->histories()->create([
            'aksi' => $aksi,
            'judul_ringkas' => sprintf(
                '%s transaksi %s sebesar %s',
                Str::title((string) ($this->arus ?: $this->inferArusFallback())),
                $this->kategori_label,
                BoardingKeuanganSiswa::formatRupiah((int) $this->nominal),
            ),
            'snapshot' => [
                'tanggal_transaksi' => $this->tanggal_transaksi?->format('Y-m-d'),
                'arus' => $this->arus ?: $this->inferArusFallback(),
                'kategori_label' => $this->kategori_label,
                'jenis_transaksi' => $this->jenis_transaksi,
                'boarding_keuangan_kategori_id' => $this->boarding_keuangan_kategori_id,
                'nominal' => (int) $this->nominal,
                'periode_bulan' => $this->periode_bulan,
                'periode_tahun' => $this->periode_tahun,
                'keterangan' => $this->keterangan,
                'created_by' => $this->created_by,
                'updated_by' => $this->updated_by,
            ],
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
            'created_at' => now(),
        ]);
    }

    protected function inferArusFallback(): string
    {
        if (static::kategoriRelationAvailable() && filled($this->boarding_keuangan_kategori_id)) {
            $slug = $this->resolveKategoriSlug();

            if (filled($slug)) {
                return self::isIncomingSlug((string) $slug) ? 'masuk' : 'keluar';
            }
        }

        if (($this->jenis_transaksi ?? null) === 'titipan_uang_saku') {
            return 'masuk';
        }

        $transactionType = trim((string) ($this->jenis_transaksi ?? ''));

        if ($transactionType === '') {
            return 'keluar';
        }

        $slugFromTransaction = Str::startsWith($transactionType, 'kategori:')
            ? Str::after($transactionType, 'kategori:')
            : $transactionType;

        return self::isIncomingSlug($slugFromTransaction) ? 'masuk' : 'keluar';
    }

    protected static function isIncomingSlug(string $slug): bool
    {
        $normalized = str_replace('-', '_', Str::of($slug)->trim()->lower()->value());

        return in_array($normalized, self::INCOMING_CATEGORY_SLUGS, true);
    }

    protected function resolveKategoriSlug(): ?string
    {
        if (! filled($this->boarding_keuangan_kategori_id)) {
            return null;
        }

        if ($this->relationLoaded('kategori') && ! $this->isDirty('boarding_keuangan_kategori_id')) {
            return $this->kategori?->slug;
        }

        return BoardingKeuanganKategori::query()->whereKey($this->boarding_keuangan_kategori_id)->value('slug');
    }

    public static function suggestedNominalForCategory(?int $keuanganSiswaId, ?int $kategoriId): ?int
    {
        if (! $keuanganSiswaId || ! $kategoriId || ! static::kategoriRelationAvailable()) {
            return null;
        }

        $nominal = static::query()
            ->where('boarding_keuangan_siswa_id', $keuanganSiswaId)
            ->where('boarding_keuangan_kategori_id', $kategoriId)
            ->orderByDesc('tanggal_transaksi')
            ->orderByDesc('id')
            ->value('nominal');

        return $nominal !== null ? (int) $nominal : null;
    }

    public static function preferredNominalForCategory(?int $keuanganSiswaId, ?int $kategoriId): ?int
    {
        if (! $kategoriId || ! static::kategoriRelationAvailable()) {
            return null;
        }

        if (BoardingKeuanganKategori::defaultNominalColumnAvailable()) {
            $defaultNominal = BoardingKeuanganKategori::query()
                ->whereKey($kategoriId)
                ->value('default_nominal');

            if ($defaultNominal !== null) {
                return (int) $defaultNominal;
            }
        }

        return static::suggestedNominalForCategory($keuanganSiswaId, $kategoriId);
    }

    public static function kategoriRelationAvailable(): bool
    {
        return static::kategoriColumnAvailable() && BoardingKeuanganKategori::kategoriTableAvailable();
    }

    public static function kategoriColumnAvailable(): bool
    {
        if (static::$kategoriColumnAvailable === true) {
            return true;
        }

        $available = SchemaFacade::hasTable((new static)->getTable())
            && SchemaFacade::hasColumn((new static)->getTable(), 'boarding_keuangan_kategori_id');

        if ($available) {
            static::$kategoriColumnAvailable = true;
        }

        return $available;
    }

    public static function arusColumnAvailable(): bool
    {
        if (static::$arusColumnAvailable === true) {
            return true;
        }

        $available = SchemaFacade::hasTable((new static)->getTable())
            && SchemaFacade::hasColumn((new static)->getTable(), 'arus');

        if ($available) {
            static::$arusColumnAvailable = true;
        }

        return $available;
    }

    public static function createdByColumnAvailable(): bool
    {
        if (static::$createdByColumnAvailable === true) {
            return true;
        }

        $available = SchemaFacade::hasTable((new static)->getTable())
            && SchemaFacade::hasColumn((new static)->getTable(), 'created_by');

        if ($available) {
            static::$createdByColumnAvailable = true;
        }

        return $available;
    }

    public static function updatedByColumnAvailable(): bool
    {
        if (static::$updatedByColumnAvailable === true) {
            return true;
        }

        $available = SchemaFacade::hasTable((new static)->getTable())
            && SchemaFacade::hasColumn((new static)->getTable(), 'updated_by');

        if ($available) {
            static::$updatedByColumnAvailable = true;
        }

        return $available;
    }

    public static function historyTableAvailable(): bool
    {
        if (static::$historyTableAvailable === true) {
            return true;
        }

        $available = SchemaFacade::hasTable((new BoardingKeuanganTransaksiHistory)->getTable());

        if ($available) {
            static::$historyTableAvailable = true;
        }

        return $available;
    }

    public static function flushRuntimeSchemaCache(): void
    {
        static::$kategoriColumnAvailable = null;
        static::$arusColumnAvailable = null;
        static::$createdByColumnAvailable = null;
        static::$updatedByColumnAvailable = null;
        static::$historyTableAvailable = null;
    }
}
