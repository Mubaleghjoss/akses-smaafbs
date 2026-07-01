<?php

namespace App\Models;

use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PerpustakaanLiterasiMaterial extends Model implements HasRichContent
{
    use InteractsWithRichContent;
    use SoftDeletes;

    protected $table = 'perpustakaan_literasi_materials';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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

        static::deleting(function (self $material): void {
            if ($material->isForceDeleting()) {
                return;
            }

            $material->questions()->get()->each->delete();
            $material->responses()->get()->each->delete();
            $material->similarityMatches()->get()->each->delete();
        });

        static::restoring(function (self $material): void {
            $material->questions()->withTrashed()->get()->each->restore();
            $material->responses()->withTrashed()->get()->each->restore();
            $material->similarityMatches()->withTrashed()->get()->each->restore();
        });
    }

    protected function setUpRichContent(): void
    {
        $this->registerRichContent('reading_content')
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsVisibility('public')
            ->customTextColors();
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
                $inner->whereNull('opens_at')->orWhere('opens_at', '<=', now());
            })
            ->where(function (Builder $inner): void {
                $inner->whereNull('closes_at')->orWhere('closes_at', '>=', now());
            });
    }

    public function hasResponses(): bool
    {
        return $this->responses()->exists();
    }

    public function imageUrl(): ?string
    {
        $path = static::normalizeImagePath($this->image_path, 'literasi/materials');

        if ($path === null) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/storage/'])) {
            return $path;
        }

        if (Str::startsWith($path, 'storage/')) {
            return asset($path);
        }

        return asset('storage/'.$path);
    }

    public function publicUrl(): string
    {
        return route('library.literacy.show', $this->slug);
    }

    public function readingContentHtml(): string
    {
        $content = trim((string) $this->reading_content);

        if ($content === '') {
            return '';
        }

        $html = $this->renderRichContent('reading_content');

        if (blank(strip_tags($html)) && ! Str::contains($html, '<img')) {
            return nl2br(e($content));
        }

        return $html;
    }

    public function readingContentPreview(int $limit = 180): string
    {
        $content = trim((string) $this->reading_content);

        if ($content === '') {
            return '';
        }

        $html = preg_replace(
            '/<\s*\/?(?:p|div|h[1-6]|li|br|tr|td|th|blockquote)[^>]*>/i',
            ' ',
            $this->readingContentHtml(),
        ) ?? $this->readingContentHtml();

        $text = Str::of(strip_tags($html))
            ->squish()
            ->toString();

        if ($text === '') {
            $text = Str::of(strip_tags($content))->squish()->toString();
        }

        return Str::limit($text, $limit);
    }

    public static function normalizeImagePath(mixed $value, string $defaultDirectory): ?string
    {
        $path = trim((string) $value);

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/storage/', 'storage/'])) {
            return $path;
        }

        $path = ltrim($path, '/');

        foreach (['public/storage/', 'storage/'] as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                $path = Str::after($path, $prefix);
            }
        }

        if (! str_contains($path, '/') && $defaultDirectory !== '') {
            $path = trim($defaultDirectory, '/').'/'.$path;
        }

        return $path;
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
