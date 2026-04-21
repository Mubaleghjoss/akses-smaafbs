<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Str;
use LogicException;

class BoardingKeuanganKategori extends Model
{
    protected static ?bool $kategoriTableAvailable = null;

    protected static ?bool $defaultNominalColumnAvailable = null;

    public const BUILTIN_CATEGORIES = [
        'kas_umum' => 'kas umum',
        'kas_kamar' => 'kas kamar',
        'qurban' => 'qurban',
        'isrun' => 'isrun',
    ];

    protected $table = 'boarding_keuangan_kategoris';

    protected $guarded = [];

    protected $casts = [
        'is_system' => 'boolean',
        'default_nominal' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            if ($record->getOriginal('is_system') && $record->isDirty(['is_system', 'nama', 'slug'])) {
                throw new LogicException('Kategori bawaan sistem tidak dapat diubah.');
            }
        });

        static::deleting(function (self $record): void {
            if ($record->is_system) {
                throw new LogicException('Kategori bawaan sistem tidak dapat dihapus.');
            }

            if ($record->usageCount() > 0) {
                throw new LogicException('Kategori ini masih dipakai transaksi. Ubah transaksi ke kategori lain terlebih dahulu sebelum menghapus kategori ini.');
            }
        });
    }

    public function transaksis(): HasMany
    {
        return $this->hasMany(BoardingKeuanganTransaksi::class, 'boarding_keuangan_kategori_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByDesc('is_system')
            ->orderBy('nama');
    }

    public static function createCustom(string $name, ?int $defaultNominal = null): self
    {
        if (! static::kategoriTableAvailable()) {
            throw new LogicException('Kategori keuangan belum tersedia pada skema runtime ini.');
        }

        $normalizedName = trim(Str::lower($name));
        $baseSlug = Str::slug($normalizedName, '_');

        if ($baseSlug === '') {
            $baseSlug = 'kategori';
        }
        $slug = $baseSlug;
        $suffix = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $baseSlug.'_'.$suffix;
        }

        $attributes = [
            'nama' => $normalizedName,
            'slug' => $slug,
            'is_system' => false,
        ];

        if (static::defaultNominalColumnAvailable()) {
            $attributes['default_nominal'] = $defaultNominal;
        }

        return static::query()->create($attributes);
    }

    public static function builtinSlugs(): array
    {
        return array_keys(self::BUILTIN_CATEGORIES);
    }

    public static function ensureBuiltinsSeeded(): void
    {
        if (! static::kategoriTableAvailable()) {
            return;
        }

        foreach (self::BUILTIN_CATEGORIES as $slug => $name) {
            static::query()->updateOrCreate(
                ['slug' => $slug],
                ['nama' => $name, 'is_system' => true],
            );
        }
    }

    public static function idBySlug(string $slug): ?int
    {
        if (! static::kategoriTableAvailable()) {
            return null;
        }

        return static::query()->where('slug', $slug)->value('id');
    }

    public static function searchOptionIds(
        string $search = '',
        int $limit = 50,
        array $excludeSlugs = [],
        ?bool $system = null,
    ): array {
        return static::searchOptions(
            search: $search,
            limit: $limit,
            excludeSlugs: $excludeSlugs,
            system: $system,
            keyColumn: 'id',
        );
    }

    public static function searchOptionSlugs(
        string $search = '',
        int $limit = 50,
        array $excludeSlugs = [],
        ?bool $system = null,
    ): array {
        return static::searchOptions(
            search: $search,
            limit: $limit,
            excludeSlugs: $excludeSlugs,
            system: $system,
            keyColumn: 'slug',
        );
    }

    public static function resolveOptionIdLabel(mixed $value): ?string
    {
        $id = (int) $value;

        if ($id <= 0 || ! static::kategoriTableAvailable()) {
            return null;
        }

        $label = static::query()->whereKey($id)->value('nama');

        return filled($label) ? ucfirst((string) $label) : null;
    }

    public static function resolveOptionSlugLabel(mixed $value): ?string
    {
        $slug = trim((string) $value);

        if ($slug === '' || ! static::kategoriTableAvailable()) {
            return null;
        }

        $label = static::query()->where('slug', $slug)->value('nama');

        return filled($label) ? ucfirst((string) $label) : null;
    }

    public static function filterSlugOptions(
        string $search = '',
        int $limit = 50,
        array $excludeSlugs = [],
        ?bool $system = null,
    ): array {
        return static::searchOptionSlugs(
            search: $search,
            limit: $limit,
            excludeSlugs: $excludeSlugs,
            system: $system,
        );
    }

    public function usageCount(): int
    {
        if (! BoardingKeuanganTransaksi::kategoriRelationAvailable()) {
            return 0;
        }

        return $this->transaksis()->count();
    }

    public static function kategoriTableAvailable(): bool
    {
        return static::$kategoriTableAvailable ??= SchemaFacade::hasTable((new static)->getTable());
    }

    public static function defaultNominalColumnAvailable(): bool
    {
        if (! static::kategoriTableAvailable()) {
            return false;
        }

        return static::$defaultNominalColumnAvailable ??= SchemaFacade::hasColumn((new static)->getTable(), 'default_nominal');
    }

    public static function flushRuntimeSchemaCache(): void
    {
        static::$kategoriTableAvailable = null;
        static::$defaultNominalColumnAvailable = null;
    }

    protected static function searchOptions(
        string $search,
        int $limit,
        array $excludeSlugs,
        ?bool $system,
        string $keyColumn,
    ): array {
        if (! static::kategoriTableAvailable()) {
            return [];
        }

        $query = static::query();

        if ($system !== null) {
            $query->where('is_system', $system);
        }

        if ($excludeSlugs !== []) {
            $query->whereNotIn('slug', $excludeSlugs);
        }

        $search = trim($search);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%');
            });
        }

        return $query
            ->ordered()
            ->limit($limit)
            ->get([$keyColumn, 'nama'])
            ->mapWithKeys(fn (self $record): array => [
                $record->getAttribute($keyColumn) => ucfirst((string) $record->nama),
            ])
            ->all();
    }
}
