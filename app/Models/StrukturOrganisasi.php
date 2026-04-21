<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Str;
use LogicException;

class StrukturOrganisasi extends Model
{
    public const CATEGORY_SCHOOL = 'sekolah';

    public const CATEGORY_COMMITTEE = 'komite';

    protected $table = 'struktur_organisasis';

    protected $guarded = [];

    protected $casts = [
        'kategori' => 'string',
        'periode_tahun' => 'integer',
        'periode_label' => 'string',
        'parent_id' => 'integer',
        'homepage_parent_id' => 'integer',
        'guru_tendik_id' => 'integer',
        'urutan' => 'integer',
        'homepage_row' => 'integer',
        'homepage_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected ?int $previousParentIdForResequence = null;

    protected ?string $previousCategoryForResequence = null;

    protected ?int $previousPeriodYearForResequence = null;

    /** @var array<int, int> */
    protected static array $levelCache = [];

    protected static ?bool $categoryColumnAvailableCache = null;

    protected static ?bool $periodColumnAvailableCache = null;

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            $record->flushLevelCacheForBranch();
            $record->rememberPreviousParentBeforeSave();
            $record->ensureCategoryDefault();
            $record->ensureCommitteePeriodDefaults();
            $record->guardAgainstInvalidParent();
            $record->guardAgainstInvalidHomepageParent();
            $record->assignSiblingOrderWhenMissing();
        });

        static::saved(function (self $record): void {
            $record->flushLevelCacheForBranch();

            if ($record->shouldResequenceAfterSave()) {
                $record->resequenceAffectedBranchesAfterSave();
            }
        });

        static::deleting(function (self $record): void {
            if ($record->children()->exists()) {
                throw new LogicException('Struktur yang masih memiliki cabang tidak dapat dihapus. Pindahkan atau hapus cabangnya terlebih dahulu.');
            }
        });

        static::deleted(function (self $record): void {
            $record->flushLevelCacheForBranch();
            static::resequenceSiblings(
                $record->parent_id !== null ? (int) $record->parent_id : null,
                $record->resolvedCategory(),
                $record->resolvedPeriodYear(),
            );
        });
    }

    public function parent(): BelongsTo
    {
        $relation = $this->belongsTo(self::class, 'parent_id');

        if (static::categoryColumnAvailable()) {
            $relation->where('kategori', $this->resolvedCategory());
        }

        if ($this->requiresCommitteePeriodScope()) {
            $relation->where('periode_tahun', $this->resolvedPeriodYear());
        }

        return $relation;
    }

    public function children(): HasMany
    {
        $relation = $this->hasMany(self::class, 'parent_id')->ordered();

        if (static::categoryColumnAvailable()) {
            $relation->where('kategori', $this->resolvedCategory());
        }

        if ($this->requiresCommitteePeriodScope()) {
            $relation->where('periode_tahun', $this->resolvedPeriodYear());
        }

        return $relation;
    }

    public function homepageParent(): BelongsTo
    {
        $relation = $this->belongsTo(self::class, 'homepage_parent_id');

        if (static::categoryColumnAvailable()) {
            $relation->where('kategori', $this->resolvedCategory());
        }

        if ($this->requiresCommitteePeriodScope()) {
            $relation->where('periode_tahun', $this->resolvedPeriodYear());
        }

        return $relation;
    }

    public function guruTendik(): BelongsTo
    {
        return $this->belongsTo(GuruTendik::class, 'guru_tendik_id');
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForParent(Builder $query, ?int $parentId): Builder
    {
        return $parentId === null
            ? $query->whereNull('parent_id')
            : $query->where('parent_id', $parentId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('urutan')
            ->orderBy('id');
    }

    public function scopeForCategory(Builder $query, ?string $category): Builder
    {
        if (! filled($category) || ! static::categoryColumnAvailable()) {
            return $query;
        }

        return $query->where('kategori', $category);
    }

    public function scopeForPeriod(Builder $query, ?int $periodYear, ?string $category = null): Builder
    {
        if (! static::periodColumnAvailable()) {
            return $query;
        }

        if (filled($category) && $category !== static::CATEGORY_COMMITTEE) {
            return $query;
        }

        if (! filled($periodYear)) {
            return $query;
        }

        return $query->where('periode_tahun', (int) $periodYear);
    }

    public function scopeForHomepage(Builder $query, ?string $category = null, ?int $periodYear = null): Builder
    {
        return $query
            ->forCategory($category)
            ->forPeriod($periodYear, $category)
            ->roots()
            ->ordered()
            ->select(static::homepageColumns());
    }

    public static function homepageTree(?string $category = null, ?int $periodYear = null): Collection
    {
        $nodes = static::query()
            ->forCategory($category)
            ->forPeriod($periodYear, $category)
            ->ordered()
            ->get(static::homepageColumns());

        $grouped = $nodes->groupBy(fn (self $node): string => $node->effectiveHomepageParentId() === null ? 'root' : (string) $node->effectiveHomepageParentId());

        $buildBranch = function (?int $parentId) use (&$buildBranch, $grouped): Collection {
            $key = $parentId === null ? 'root' : (string) $parentId;

            return static::sortHomepageNodes(collect($grouped->get($key, collect())))
                ->values()
                ->map(function (self $node) use (&$buildBranch): self {
                    $node->setRelation('children', $buildBranch($node->id));

                    return $node;
                });
        };

        return $buildBranch(null);
    }

    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_SCHOOL => 'Sekolah',
            self::CATEGORY_COMMITTEE => 'Komite',
        ];
    }

    public static function categoryLabel(?string $category): string
    {
        return static::categoryOptions()[(string) $category] ?? ($category ?: 'Sekolah');
    }

    public static function categoryColumnAvailable(): bool
    {
        if (static::$categoryColumnAvailableCache !== null) {
            return static::$categoryColumnAvailableCache;
        }

        $table = (new static)->getTable();

        if (! SchemaFacade::hasTable($table)) {
            return static::$categoryColumnAvailableCache = false;
        }

        return static::$categoryColumnAvailableCache = SchemaFacade::hasColumn($table, 'kategori');
    }

    public static function periodColumnAvailable(): bool
    {
        if (static::$periodColumnAvailableCache !== null) {
            return static::$periodColumnAvailableCache;
        }

        $table = (new static)->getTable();

        if (! SchemaFacade::hasTable($table)) {
            return static::$periodColumnAvailableCache = false;
        }

        return static::$periodColumnAvailableCache = SchemaFacade::hasColumn($table, 'periode_tahun');
    }

    public function resolvedCategory(): ?string
    {
        return static::resolveCategoryValue($this->kategori ?? null);
    }

    public function resolvedPeriodYear(): ?int
    {
        if (! static::periodColumnAvailable() || $this->resolvedCategory() !== static::CATEGORY_COMMITTEE) {
            return null;
        }

        $periodYear = filled($this->periode_tahun) ? (int) $this->periode_tahun : static::defaultCommitteePeriodYear();

        return $periodYear > 0 ? $periodYear : static::defaultCommitteePeriodYear();
    }

    public function resolvedPeriodLabel(): ?string
    {
        return static::formatCommitteePeriodLabel(
            $this->resolvedPeriodYear(),
            $this->periode_label,
        );
    }

    public static function committeePeriods(): Collection
    {
        if (! static::periodColumnAvailable()) {
            return collect();
        }

        return static::query()
            ->forCategory(static::CATEGORY_COMMITTEE)
            ->whereNotNull('periode_tahun')
            ->orderByDesc('periode_tahun')
            ->get(['periode_tahun', 'periode_label'])
            ->unique('periode_tahun')
            ->values()
            ->map(fn (self $record): array => [
                'year' => filled($record->periode_tahun) ? (int) $record->periode_tahun : null,
                'label' => static::formatCommitteePeriodLabel(
                    filled($record->periode_tahun) ? (int) $record->periode_tahun : null,
                    $record->periode_label,
                ),
            ]);
    }

    public static function formatCommitteePeriodLabel(?int $periodYear, ?string $periodLabel): ?string
    {
        $label = trim((string) $periodLabel);

        if ($label === '' && $periodYear === null) {
            return null;
        }

        if ($label === '') {
            return 'Periode '.$periodYear;
        }

        if ($periodYear !== null && ! Str::contains($label, (string) $periodYear)) {
            return $periodYear.' · '.$label;
        }

        return $label;
    }

    /**
     * @return array<int, string>
     */
    protected static function homepageColumns(): array
    {
        $columns = ['id', 'parent_id', 'homepage_parent_id', 'homepage_row', 'homepage_order', 'guru_tendik_id', 'jabatan', 'nama', 'foto', 'urutan'];

        if (static::categoryColumnAvailable()) {
            $columns[] = 'kategori';
        }

        if (static::periodColumnAvailable()) {
            $columns[] = 'periode_tahun';
            $columns[] = 'periode_label';
        }

        return $columns;
    }

    public function effectiveHomepageParentId(): ?int
    {
        if (filled($this->homepage_parent_id)) {
            return (int) $this->homepage_parent_id;
        }

        return filled($this->parent_id) ? (int) $this->parent_id : null;
    }

    public function homepageRowNumber(): int
    {
        return max(1, (int) ($this->homepage_row ?: 1));
    }

    public function homepageOrderNumber(): int
    {
        return max(1, (int) ($this->homepage_order ?: $this->urutan ?: 1));
    }

    public function descendantIds(): array
    {
        $ids = [];
        $pending = $this->children()->pluck('id')->all();

        while ($pending !== []) {
            $childId = array_shift($pending);

            if ($childId === null || in_array($childId, $ids, true)) {
                continue;
            }

            $ids[] = $childId;

            $nested = static::query()->where('parent_id', $childId)->pluck('id')->all();

            foreach ($nested as $nestedId) {
                if (! in_array($nestedId, $ids, true)) {
                    $pending[] = $nestedId;
                }
            }
        }

        return $ids;
    }

    public function branchIds(): array
    {
        return array_values(array_unique([
            (int) $this->getKey(),
            ...$this->descendantIds(),
        ]));
    }

    public function levelDepth(): int
    {
        $recordId = (int) $this->getKey();

        if ($recordId > 0 && array_key_exists($recordId, static::$levelCache)) {
            return static::$levelCache[$recordId];
        }

        $depth = 0;
        $parentId = filled($this->parent_id) ? (int) $this->parent_id : null;
        $visitedParentIds = [];

        while ($parentId !== null) {
            if (in_array($parentId, $visitedParentIds, true)) {
                break;
            }

            $visitedParentIds[] = $parentId;
            $depth++;

            if (array_key_exists($parentId, static::$levelCache)) {
                $depth += static::$levelCache[$parentId];
                break;
            }

            $parentId = static::query()
                ->whereKey($parentId)
                ->value('parent_id');

            $parentId = filled($parentId) ? (int) $parentId : null;
        }

        if ($recordId > 0) {
            static::$levelCache[$recordId] = $depth;
        }

        return $depth;
    }

    public function levelNumber(): int
    {
        return $this->levelDepth() + 1;
    }

    public function canIndentUnderPreviousSibling(): bool
    {
        return $this->previousSibling() !== null;
    }

    public function canOutdentToParentLevel(): bool
    {
        return filled($this->parent_id) && $this->parent()->exists();
    }

    public function canMoveUpWithinSiblings(): bool
    {
        return $this->siblingScopedQuery()
            ->ordered()
            ->where(function (Builder $query): void {
                $query
                    ->where('urutan', '<', (int) $this->urutan)
                    ->orWhere(function (Builder $sameOrderQuery): void {
                        $sameOrderQuery
                            ->where('urutan', (int) $this->urutan)
                            ->where('id', '<', (int) $this->getKey());
                    });
            })
            ->exists();
    }

    public function canMoveDownWithinSiblings(): bool
    {
        return $this->siblingScopedQuery()
            ->ordered()
            ->where(function (Builder $query): void {
                $query
                    ->where('urutan', '>', (int) $this->urutan)
                    ->orWhere(function (Builder $sameOrderQuery): void {
                        $sameOrderQuery
                            ->where('urutan', (int) $this->urutan)
                            ->where('id', '>', (int) $this->getKey());
                    });
            })
            ->exists();
    }

    public function moveUpWithinSiblings(): bool
    {
        return $this->moveWithinSiblings(-1);
    }

    public function moveDownWithinSiblings(): bool
    {
        return $this->moveWithinSiblings(1);
    }

    public function indentUnderPreviousSibling(): bool
    {
        $previousSibling = $this->previousSibling();

        if (! $previousSibling) {
            return false;
        }

        $this->parent_id = (int) $previousSibling->getKey();
        $this->urutan = null;

        $saved = $this->save();

        $this->refresh();

        return $saved;
    }

    public function outdentToParentLevel(): bool
    {
        if (! filled($this->parent_id)) {
            return false;
        }

        /** @var self|null $parent */
        $parent = $this->sameCategoryQuery()->find((int) $this->parent_id, ['id', 'parent_id', 'kategori']);

        if (! $parent) {
            return false;
        }

        $targetParentId = filled($parent->parent_id) ? (int) $parent->parent_id : null;
        $orderedIds = $this->siblingScopedQueryForParent($targetParentId)
            ->ordered()
            ->pluck('id')
            ->filter(fn (mixed $id): bool => (int) $id !== (int) $this->getKey())
            ->values()
            ->all();

        $parentIndex = array_search((int) $parent->getKey(), $orderedIds, true);
        $insertIndex = $parentIndex === false ? count($orderedIds) : $parentIndex + 1;

        array_splice($orderedIds, $insertIndex, 0, [(int) $this->getKey()]);

        $this->parent_id = $targetParentId;
        $this->urutan = $insertIndex + 1;

        $saved = $this->save();

        static::resequenceOrderedIds($orderedIds);
        $this->refresh();

        return $saved;
    }

    public static function resequenceSiblings(?int $parentId, ?string $category = null, ?int $periodYear = null): void
    {
        $siblings = static::query()
            ->forCategory($category)
            ->forPeriod($periodYear, $category)
            ->forParent($parentId)
            ->ordered()
            ->get(['id']);

        foreach ($siblings as $index => $sibling) {
            static::query()
                ->whereKey($sibling->id)
                ->update(['urutan' => $index + 1]);
        }
    }

    protected function assignSiblingOrderWhenMissing(): void
    {
        if (filled($this->urutan)) {
            $this->urutan = max(1, (int) $this->urutan);

            return;
        }

        $this->urutan = ((int) $this->siblingScopedQuery()
            ->max('urutan')) + 1;
    }

    protected function rememberPreviousParentBeforeSave(): void
    {
        if (! $this->exists) {
            $this->previousParentIdForResequence = null;
            $this->previousCategoryForResequence = null;
            $this->previousPeriodYearForResequence = null;

            return;
        }

        $originalParentId = $this->getOriginal('parent_id');
        $this->previousParentIdForResequence = filled($originalParentId) ? (int) $originalParentId : null;
        $this->previousCategoryForResequence = static::resolveCategoryValue($this->getOriginal('kategori'));
        $originalPeriodYear = $this->getOriginal('periode_tahun');
        $this->previousPeriodYearForResequence = filled($originalPeriodYear) ? (int) $originalPeriodYear : null;
    }

    protected function resequenceAffectedBranchesAfterSave(): void
    {
        $currentParentId = filled($this->parent_id) ? (int) $this->parent_id : null;
        $currentCategory = $this->resolvedCategory();
        $currentPeriodYear = $this->resolvedPeriodYear();

        static::resequenceSiblings($currentParentId, $currentCategory, $currentPeriodYear);

        if (
            $this->previousParentIdForResequence !== $currentParentId
            || $this->previousCategoryForResequence !== $currentCategory
            || $this->previousPeriodYearForResequence !== $currentPeriodYear
        ) {
            static::resequenceSiblings(
                $this->previousParentIdForResequence,
                $this->previousCategoryForResequence,
                $this->previousPeriodYearForResequence,
            );
        }

        $this->previousParentIdForResequence = null;
        $this->previousCategoryForResequence = null;
        $this->previousPeriodYearForResequence = null;
    }

    protected function shouldResequenceAfterSave(): bool
    {
        return $this->wasChanged('parent_id')
            || $this->wasChanged('urutan')
            || $this->wasChanged('kategori')
            || (static::periodColumnAvailable() && $this->wasChanged('periode_tahun'));
    }

    protected function moveWithinSiblings(int $direction): bool
    {
        $siblings = $this->siblingScopedQuery()
            ->ordered()
            ->get(['id']);

        $orderedIds = $siblings->pluck('id')->values()->all();
        $currentIndex = array_search((int) $this->getKey(), $orderedIds, true);

        if ($currentIndex === false) {
            return false;
        }

        $targetIndex = $currentIndex + $direction;

        if ($targetIndex < 0 || $targetIndex >= count($orderedIds)) {
            return false;
        }

        [$orderedIds[$currentIndex], $orderedIds[$targetIndex]] = [$orderedIds[$targetIndex], $orderedIds[$currentIndex]];

        foreach ($orderedIds as $index => $id) {
            static::query()->whereKey($id)->update(['urutan' => $index + 1]);
        }

        $this->refresh();

        return true;
    }

    protected static function resequenceOrderedIds(array $orderedIds): void
    {
        foreach (array_values($orderedIds) as $index => $id) {
            static::query()->whereKey($id)->update(['urutan' => $index + 1]);
        }
    }

    protected function previousSibling(): ?self
    {
        return $this->siblingScopedQuery()
            ->where(function (Builder $query): void {
                $query
                    ->where('urutan', '<', (int) $this->urutan)
                    ->orWhere(function (Builder $sameOrderQuery): void {
                        $sameOrderQuery
                            ->where('urutan', (int) $this->urutan)
                            ->where('id', '<', (int) $this->getKey());
                    });
            })
            ->orderByDesc('urutan')
            ->orderByDesc('id')
            ->first();
    }

    protected function flushLevelCacheForBranch(): void
    {
        if (! $this->exists) {
            static::$levelCache = [];

            return;
        }

        foreach ([$this->getKey(), ...$this->descendantIds()] as $id) {
            unset(static::$levelCache[(int) $id]);
        }
    }

    protected function guardAgainstInvalidParent(): void
    {
        if (! filled($this->parent_id)) {
            return;
        }

        $parentId = (int) $this->parent_id;

        /** @var self|null $parent */
        $parent = static::query()->whereKey($parentId)->first(['id', 'parent_id', 'kategori', 'periode_tahun']);

        if (! $parent) {
            throw new LogicException('Atasan langsung yang dipilih tidak ditemukan.');
        }

        if (
            static::categoryColumnAvailable()
            && $parent->resolvedCategory() !== $this->resolvedCategory()
        ) {
            throw new LogicException('Atasan langsung harus berada pada kategori struktur yang sama.');
        }

        if (
            $this->requiresCommitteePeriodScope()
            && $parent->resolvedPeriodYear() !== $this->resolvedPeriodYear()
        ) {
            throw new LogicException('Atasan langsung komite harus berada pada periode yang sama.');
        }

        if ($this->exists && $parentId === (int) $this->getKey()) {
            throw new LogicException('Struktur organisasi tidak boleh menjadi atasan dirinya sendiri.');
        }

        if ($this->exists && in_array($parentId, $this->descendantIds(), true)) {
            throw new LogicException('Struktur organisasi tidak boleh dipindahkan ke cabang turunannya sendiri.');
        }

        $currentParentId = filled($parent->parent_id) ? (int) $parent->parent_id : null;
        $selfId = $this->exists ? (int) $this->getKey() : null;

        while ($currentParentId !== null) {
            if ($selfId !== null && (int) $currentParentId === $selfId) {
                throw new LogicException('Struktur organisasi tidak boleh membentuk siklus hierarki.');
            }

            $currentParentId = static::query()->whereKey($currentParentId)->value('parent_id');
        }
    }

    protected function guardAgainstInvalidHomepageParent(): void
    {
        if (! filled($this->homepage_parent_id)) {
            return;
        }

        $homepageParentId = (int) $this->homepage_parent_id;

        /** @var self|null $homepageParent */
        $homepageParent = static::query()
            ->whereKey($homepageParentId)
            ->first(['id', 'parent_id', 'homepage_parent_id', 'kategori', 'periode_tahun']);

        if (! $homepageParent) {
            throw new LogicException('Atasan tampilan homepage yang dipilih tidak ditemukan.');
        }

        if (
            static::categoryColumnAvailable()
            && $homepageParent->resolvedCategory() !== $this->resolvedCategory()
        ) {
            throw new LogicException('Atasan tampilan homepage harus berada pada kategori struktur yang sama.');
        }

        if (
            $this->requiresCommitteePeriodScope()
            && $homepageParent->resolvedPeriodYear() !== $this->resolvedPeriodYear()
        ) {
            throw new LogicException('Atasan tampilan homepage komite harus berada pada periode yang sama.');
        }

        if ($this->exists && $homepageParentId === (int) $this->getKey()) {
            throw new LogicException('Struktur organisasi tidak boleh menjadi atasan tampilan dirinya sendiri.');
        }

        $selfId = $this->exists ? (int) $this->getKey() : null;
        $visitedParentIds = [];
        $currentParentId = $homepageParentId;

        while ($currentParentId !== null) {
            if (in_array($currentParentId, $visitedParentIds, true)) {
                throw new LogicException('Atasan tampilan homepage membentuk siklus hierarki.');
            }

            if ($selfId !== null && $currentParentId === $selfId) {
                throw new LogicException('Atasan tampilan homepage tidak boleh mengarah ke cabang dirinya sendiri.');
            }

            $visitedParentIds[] = $currentParentId;

            /** @var self|null $currentParent */
            $currentParent = static::query()
                ->whereKey($currentParentId)
                ->first(['id', 'parent_id', 'homepage_parent_id']);

            if (! $currentParent) {
                break;
            }

            $currentParentId = $currentParent->effectiveHomepageParentId();
        }
    }

    protected static function sortHomepageNodes(Collection $nodes): Collection
    {
        return $nodes
            ->sort(function (self $left, self $right): int {
                $leftTuple = [$left->homepageRowNumber(), $left->homepageOrderNumber(), (int) $left->urutan, (int) $left->getKey()];
                $rightTuple = [$right->homepageRowNumber(), $right->homepageOrderNumber(), (int) $right->urutan, (int) $right->getKey()];

                return $leftTuple <=> $rightTuple;
            })
            ->values();
    }

    protected function ensureCategoryDefault(): void
    {
        if (! static::categoryColumnAvailable()) {
            return;
        }

        $this->kategori = $this->resolvedCategory();
    }

    protected function ensureCommitteePeriodDefaults(): void
    {
        if (! static::periodColumnAvailable()) {
            return;
        }

        if ($this->resolvedCategory() !== static::CATEGORY_COMMITTEE) {
            $this->periode_tahun = null;
            $this->periode_label = null;

            return;
        }

        $periodYear = filled($this->periode_tahun) ? (int) $this->periode_tahun : static::defaultCommitteePeriodYear();
        $periodYear = $periodYear > 0 ? $periodYear : static::defaultCommitteePeriodYear();

        $this->periode_tahun = $periodYear;
        $this->periode_label = trim((string) ($this->periode_label ?? '')) !== ''
            ? trim((string) $this->periode_label)
            : (string) $periodYear;
    }

    protected function sameCategoryQuery(): Builder
    {
        $query = static::query()->forCategory($this->resolvedCategory());

        if ($this->requiresCommitteePeriodScope()) {
            $query->where('periode_tahun', $this->resolvedPeriodYear());
        }

        return $query;
    }

    protected function siblingScopedQuery(?int $parentId = null): Builder
    {
        if (func_num_args() === 0) {
            $parentId = filled($this->parent_id) ? (int) $this->parent_id : null;
        }

        return $this->sameCategoryQuery()->forParent($parentId);
    }

    protected function siblingScopedQueryForParent(?int $parentId): Builder
    {
        return $this->sameCategoryQuery()->forParent($parentId);
    }

    protected static function resolveCategoryValue(mixed $category): ?string
    {
        if (! static::categoryColumnAvailable()) {
            return null;
        }

        $value = trim((string) $category);

        return $value !== '' ? $value : static::CATEGORY_SCHOOL;
    }

    protected function requiresCommitteePeriodScope(): bool
    {
        return static::periodColumnAvailable() && $this->resolvedCategory() === static::CATEGORY_COMMITTEE;
    }

    protected static function defaultCommitteePeriodYear(): int
    {
        return (int) now()->format('Y');
    }
}
