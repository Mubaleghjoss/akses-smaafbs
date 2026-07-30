<?php

namespace App\Models;

use App\Models\Assessment\HomeroomAssignment;
use App\Models\Assessment\TeachingAssignment;
use App\Support\Admin\Dashboard\DashboardCacheSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class GuruTendik extends Model
{
    public const JENIS_PTK_OPTIONS = [
        'Guru' => 'Guru',
        'Tendik' => 'Tendik',
        'Pamong' => 'Pamong',
    ];

    public const JK_OPTIONS = [
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
    ];

    protected $table = 'guru_tendik';

    protected $guarded = [];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @var array<string, string>|null
     */
    protected static ?array $statusOptionsCache = null;

    protected static function booted(): void
    {
        $invalidateDashboardCaches = static function (self $record): void {
            DashboardCacheSupport::forgetModule('guru_tendik');
            DashboardCacheSupport::forgetModule('user_credentials');
        };

        static::saved($invalidateDashboardCaches);
        static::deleted($invalidateDashboardCaches);
    }

    public function scopeVisibleToUser(Builder $query, mixed $user): Builder
    {
        if (! $user instanceof User) {
            return $query;
        }

        if ($user->hasFullAdminAccess()) {
            return $query;
        }

        if (! $user->isGuru()) {
            return $query;
        }

        if (! $user->guru_tendik_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereKey($user->guru_tendik_id);
    }

    public function userAccount(): HasOne
    {
        return $this->hasOne(User::class, 'guru_tendik_id');
    }

    public function berkasGurus(): HasMany
    {
        return $this->hasMany(BerkasGuru::class, 'guru_id');
    }

    public function tugasTambahan(): HasMany
    {
        return $this->hasMany(GuruTendikTugasTambahan::class, 'guru_tendik_id')->orderByDesc('tmt');
    }

    public function tugasTambahanAktif(): HasMany
    {
        return $this->tugasTambahan()
            ->whereDate('tmt', '<=', now()->toDateString())
            ->where(function (Builder $query): void {
                $query->whereNull('tst')->orWhereDate('tst', '>=', now()->toDateString());
            });
    }

    public function assessmentTeachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'teacher_id');
    }

    public function assessmentHomeroomAssignments(): HasMany
    {
        return $this->hasMany(HomeroomAssignment::class, 'teacher_id');
    }

    public function strukturOrganisasiNode(): HasOne
    {
        return $this->hasOne(StrukturOrganisasi::class, 'guru_tendik_id')->ordered();
    }

    public static function jenisPtkOptions(): array
    {
        return self::JENIS_PTK_OPTIONS;
    }

    public static function jkOptions(): array
    {
        return self::JK_OPTIONS;
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

    public function hasActiveTugasTambahan(?Carbon $today = null): bool
    {
        $today ??= now();

        return $this->tugasTambahan()
            ->whereDate('tmt', '<=', $today->toDateString())
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('tst')->orWhereDate('tst', '>=', $today->toDateString());
            })
            ->exists();
    }

    public function latestTugasTambahan(): ?GuruTendikTugasTambahan
    {
        return $this->tugasTambahan()->first();
    }
}
