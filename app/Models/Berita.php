<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class Berita extends Model
{
    protected static ?bool $tableAvailableCache = null;

    public const TRACKER_PHASES = [
        'persiapan' => 'Persiapan',
        'acara' => 'Acara',
        'selesai' => 'Selesai',
    ];

    protected $table = 'berita';

    protected $fillable = [
        'judul',
        'konten',
        'gambar',
        'id_admin',
        'status',
        'tanggal_berita',
        'tracker_phase',
        'tracker_progress_percent',
        'tracker_update_text',
        'tracker_documentation_media',
        'tracker_live_url',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'tanggal_berita' => 'date',
        'tracker_progress_percent' => 'integer',
        'tracker_documentation_media' => 'array',
    ];

    public function updates(): HasMany
    {
        return $this->hasMany(BeritaUpdate::class, 'berita_id')
            ->orderByDesc('tanggal_update')
            ->orderByDesc('id');
    }

    public function latestUpdate(): HasOne
    {
        return $this->hasOne(BeritaUpdate::class, 'berita_id')
            ->ofMany([
                'tanggal_update' => 'max',
                'id' => 'max',
            ]);
    }

    public static function updatesTableAvailable(): bool
    {
        return SchemaFacade::hasTable('berita_updates');
    }

    public function latestTimelineUpdate(): ?BeritaUpdate
    {
        if (! static::updatesTableAvailable()) {
            return null;
        }

        return $this->updates()->first();
    }

    public function timelineUpdatesForPublic()
    {
        if (! static::updatesTableAvailable()) {
            return collect();
        }

        return $this->updates()->get();
    }

    public function syncTrackerSnapshotFromUpdates(): void
    {
        if (! static::updatesTableAvailable()) {
            return;
        }

        $latestUpdate = $this->updates()->first();

        if (! $latestUpdate) {
            $attributes = [];

            if (static::trackerPhaseColumnAvailable()) {
                $attributes['tracker_phase'] = null;
            }

            if (static::trackerProgressPercentColumnAvailable()) {
                $attributes['tracker_progress_percent'] = null;
            }

            if (static::trackerUpdateTextColumnAvailable()) {
                $attributes['tracker_update_text'] = null;
            }

            if (static::trackerDocumentationMediaColumnAvailable()) {
                $attributes['tracker_documentation_media'] = null;
            }

            if (static::trackerLiveUrlColumnAvailable()) {
                $attributes['tracker_live_url'] = null;
            }

            if ($attributes !== []) {
                $this->updateQuietly($attributes);
            }

            return;
        }

        $attributes = [];

        if (static::trackerPhaseColumnAvailable()) {
            $attributes['tracker_phase'] = $latestUpdate->phase;
        }

        if (static::trackerProgressPercentColumnAvailable()) {
            $attributes['tracker_progress_percent'] = $latestUpdate->progress_percent;
        }

        if (static::trackerUpdateTextColumnAvailable()) {
            $attributes['tracker_update_text'] = $latestUpdate->update_text;
        }

        if (static::trackerDocumentationMediaColumnAvailable()) {
            $attributes['tracker_documentation_media'] = $latestUpdate->documentation_media;
        }

        if (static::trackerLiveUrlColumnAvailable()) {
            $attributes['tracker_live_url'] = $latestUpdate->live_url;
        }

        if ($attributes !== []) {
            $this->updateQuietly($attributes);
        }
    }

    public function getTrackerPhaseLabelAttribute(): ?string
    {
        if (! static::trackerPhaseColumnAvailable() || ! filled($this->tracker_phase)) {
            return null;
        }

        return self::TRACKER_PHASES[$this->tracker_phase] ?? ucfirst((string) $this->tracker_phase);
    }

    public static function hasAnyTrackerColumn(): bool
    {
        return static::trackerPhaseColumnAvailable()
            || static::trackerProgressPercentColumnAvailable()
            || static::trackerUpdateTextColumnAvailable()
            || static::trackerDocumentationMediaColumnAvailable()
            || static::trackerLiveUrlColumnAvailable();
    }

    public static function trackerPhaseColumnAvailable(): bool
    {
        return static::tableAvailable()
            && SchemaFacade::hasColumn((new static)->getTable(), 'tracker_phase');
    }

    public static function trackerProgressPercentColumnAvailable(): bool
    {
        return static::tableAvailable()
            && SchemaFacade::hasColumn((new static)->getTable(), 'tracker_progress_percent');
    }

    public static function trackerUpdateTextColumnAvailable(): bool
    {
        return static::tableAvailable()
            && SchemaFacade::hasColumn((new static)->getTable(), 'tracker_update_text');
    }

    public static function trackerDocumentationMediaColumnAvailable(): bool
    {
        return static::tableAvailable()
            && SchemaFacade::hasColumn((new static)->getTable(), 'tracker_documentation_media');
    }

    public static function trackerLiveUrlColumnAvailable(): bool
    {
        return static::tableAvailable()
            && SchemaFacade::hasColumn((new static)->getTable(), 'tracker_live_url');
    }

    protected static function tableAvailable(): bool
    {
        return static::$tableAvailableCache ??= SchemaFacade::hasTable((new static)->getTable());
    }

    public static function flushSchemaColumnAvailabilityCache(): void
    {
        static::$tableAvailableCache = null;
    }

    public static function searchOptionLabels(string $search = '', int $limit = 50): array
    {
        $query = static::query()
            ->select(['id', 'judul', 'tanggal_berita', 'created_at']);
        $search = trim($search);

        if ($search !== '') {
            $query->where('judul', 'like', '%'.$search.'%');
        }

        return $query
            ->orderByDesc('tanggal_berita')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('judul', 'id')
            ->toArray();
    }

    public static function resolveOptionLabel(mixed $value): ?string
    {
        $id = (int) $value;

        if ($id <= 0) {
            return null;
        }

        return static::query()->whereKey($id)->value('judul');
    }
}
