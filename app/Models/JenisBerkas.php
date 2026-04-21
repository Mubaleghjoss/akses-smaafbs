<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JenisBerkas extends Model
{
    protected $table = 'jenis_berkas';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @var array<string, string>|null
     */
    protected static ?array $statusOptionsCache = null;

    public static function searchOptionLabels(string $search = '', int $limit = 50): array
    {
        $query = static::query()->select(['id', 'nama_berkas', 'urutan']);
        $search = trim($search);

        if ($search !== '') {
            $query->where('nama_berkas', 'like', '%'.$search.'%');
        }

        return $query
            ->orderBy('urutan')
            ->orderBy('nama_berkas')
            ->limit($limit)
            ->pluck('nama_berkas', 'id')
            ->toArray();
    }

    public static function resolveOptionLabel(mixed $value): ?string
    {
        $id = (int) $value;

        if ($id <= 0) {
            return null;
        }

        return static::query()->whereKey($id)->value('nama_berkas');
    }

    public static function statusOptions(): array
    {
        return static::$statusOptionsCache ??= static::query()
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->orderBy('status')
            ->pluck('status', 'status')
            ->all();
    }

    public function resolvedGoogleDriveFolderName(): string
    {
        $customName = trim((string) $this->google_drive_folder_name);

        if ($customName !== '') {
            return $customName;
        }

        $fallback = trim((string) $this->nama_berkas);

        return $fallback !== '' ? $fallback : 'Berkas';
    }

    public function normalizedGoogleDriveFolderName(): string
    {
        $label = Str::of(Str::ascii($this->resolvedGoogleDriveFolderName()))
            ->replaceMatches('/[^A-Za-z0-9\-\s]/', '')
            ->squish()
            ->limit(80, '');

        return (string) ($label !== '' ? $label : 'Berkas');
    }
}
