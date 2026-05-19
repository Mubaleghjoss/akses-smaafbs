<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PerpustakaanLiterasiMaterial extends Model
{
    protected $table = 'perpustakaan_literasi_materials';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'opens_at' => 'date',
        'closes_at' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $material): void {
            $material->slug = static::uniqueSlug($material->title, $material->slug);
            $material->created_by ??= auth()->id();
            $material->updated_by ??= auth()->id();
        });

        static::updating(function (self $material): void {
            if ($material->isDirty('title') || blank($material->slug)) {
                $material->slug = static::uniqueSlug($material->title, $material->slug, $material->getKey());
            }

            $material->updated_by = auth()->id() ?: $material->updated_by;
        });
    }

    public function questions(): HasMany
    {
        return $this->hasMany(PerpustakaanLiterasiQuestion::class, 'material_id')->orderBy('sort_order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(PerpustakaanLiterasiResponse::class, 'material_id')->latest('submitted_at');
    }

    public function similarityMatches(): HasMany
    {
        return $this->hasMany(PerpustakaanLiterasiSimilarityMatch::class, 'material_id')
            ->orderByDesc('similarity_score')
            ->latest();
    }

    public function scopeAvailableForPublic(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $inner): void {
                $inner->whereNull('opens_at')->orWhereDate('opens_at', '<=', now()->toDateString());
            })
            ->where(function (Builder $inner): void {
                $inner->whereNull('closes_at')->orWhereDate('closes_at', '>=', now()->toDateString());
            });
    }

    public function hasResponses(): bool
    {
        return $this->responses()->exists();
    }

    public function imageUrl(): ?string
    {
        $path = trim((string) $this->image_path);

        return $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    public function publicUrl(): string
    {
        return route('library.literacy.show', $this->slug);
    }

    public static function uniqueSlug(?string $title, ?string $currentSlug = null, ?int $ignoreId = null): string
    {
        $base = Str::slug($currentSlug ?: $title ?: 'materi-literasi');
        $base = $base !== '' ? $base : 'materi-literasi';
        $slug = $base;
        $counter = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
