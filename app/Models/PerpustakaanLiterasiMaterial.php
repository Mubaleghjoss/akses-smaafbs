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

    public const CATEGORY_LITERACY_HABITUATION = 'literacy_habituation';

    public const CATEGORY_NUMERACY_EXCELLENCE = 'numeracy_excellence';

    public const CATEGORY_SIGAP_29_KARAKTER = 'sigap_29_karakter';

    public const GLOBAL_INSTRUCTIONS_SETTING_KEY = 'perpustakaan_literasi_default_instructions';

    public const DEFAULT_INSTRUCTIONS = "Kerjakan soal secara mandiri, jujur, dan sesuai kemampuan sendiri.\nJangan menyalin jawaban teman, membuka jawaban dari sumber lain tanpa memahami, atau meminta orang lain mengerjakan.\nJika perlu membuka layanan perpus, gunakan menu Akses Perpus di header sebelum mulai mengisi jawaban.";

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

    /**
     * @return array<string, string>
     */
    public static function programCategoryOptions(): array
    {
        return [
            self::CATEGORY_LITERACY_HABITUATION => 'Literacy Habituation Programme',
            self::CATEGORY_NUMERACY_EXCELLENCE => 'Numeracy Excellence Programme',
            self::CATEGORY_SIGAP_29_KARAKTER => 'Sigap 29 Karakter',
        ];
    }

    public static function uncategorizedProgramLabel(): string
    {
        return 'Belum Berkategori';
    }

    public function programCategoryLabel(): string
    {
        $category = trim((string) $this->program_category);

        if ($category === '') {
            return self::uncategorizedProgramLabel();
        }

        return self::programCategoryOptions()[$category] ?? Str::headline($category);
    }

    public function programCategoryColor(): string
    {
        return match ($this->program_category) {
            self::CATEGORY_LITERACY_HABITUATION => 'info',
            self::CATEGORY_NUMERACY_EXCELLENCE => 'success',
            self::CATEGORY_SIGAP_29_KARAKTER => 'warning',
            default => 'gray',
        };
    }

    public function programCategoryBadgeClasses(): string
    {
        return match ($this->program_category) {
            self::CATEGORY_LITERACY_HABITUATION => 'border-sky-200 bg-sky-50 text-sky-700',
            self::CATEGORY_NUMERACY_EXCELLENCE => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            self::CATEGORY_SIGAP_29_KARAKTER => 'border-amber-200 bg-amber-50 text-amber-700',
            default => 'border-slate-200 bg-slate-50 text-slate-600',
        };
    }

    public function videoEmbedUrl(): ?string
    {
        $url = trim((string) $this->video_url);

        if ($url === '') {
            return null;
        }

        $youtubeId = static::extractYoutubeVideoId($url);

        if ($youtubeId !== null) {
            return 'https://www.youtube.com/embed/'.$youtubeId;
        }

        $driveId = static::extractGoogleDriveFileId($url);

        if ($driveId !== null) {
            return 'https://drive.google.com/file/d/'.$driveId.'/preview';
        }

        if (Str::startsWith($url, ['https://www.youtube.com/embed/', 'https://drive.google.com/file/d/'])) {
            return $url;
        }

        return null;
    }

    public function instructionsText(): string
    {
        $instructions = trim((string) $this->instructions);

        if ($instructions === '') {
            return self::defaultInstructionsText();
        }

        return $instructions;
    }

    public function instructionsHtml(): string
    {
        return self::instructionsTextToHtml($this->instructionsText());
    }

    public static function defaultInstructionsText(): string
    {
        return trim((string) Pengaturan::value(self::GLOBAL_INSTRUCTIONS_SETTING_KEY, self::DEFAULT_INSTRUCTIONS))
            ?: self::DEFAULT_INSTRUCTIONS;
    }

    public static function defaultInstructionsHtml(): string
    {
        return self::instructionsTextToHtml(self::defaultInstructionsText());
    }

    public static function saveDefaultInstructions(string $instructions): void
    {
        Pengaturan::query()->updateOrCreate(
            ['nama_pengaturan' => self::GLOBAL_INSTRUCTIONS_SETTING_KEY],
            ['nilai_pengaturan' => trim($instructions) ?: self::DEFAULT_INSTRUCTIONS]
        );
    }

    protected static function instructionsTextToHtml(string $instructions): string
    {
        return collect(preg_split('/\R{2,}|\R/', $instructions) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->map(fn (string $line): string => '<p>'.e($line).'</p>')
            ->implode('');
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

    protected static function extractYoutubeVideoId(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if ($host !== null && Str::contains($host, 'youtu.be')) {
            return $path !== '' ? Str::before($path, '/') : null;
        }

        if ($host !== null && Str::contains($host, 'youtube.com')) {
            if (filled($query['v'] ?? null)) {
                return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $query['v']) ?: null;
            }

            if (Str::startsWith($path, 'embed/')) {
                return Str::after($path, 'embed/');
            }

            if (Str::startsWith($path, 'shorts/')) {
                return Str::after($path, 'shorts/');
            }
        }

        return null;
    }

    protected static function extractGoogleDriveFileId(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null || ! Str::contains($host, 'drive.google.com')) {
            return null;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('~/file/d/([^/]+)~', $path, $matches) === 1) {
            return $matches[1];
        }

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return filled($query['id'] ?? null) ? (string) $query['id'] : null;
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
