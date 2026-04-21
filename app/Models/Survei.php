<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Survei extends Model
{
    public const AUDIENCE_STUDENT = 'student';

    public const AUDIENCE_TEACHER = 'teacher';

    protected $table = 'surveis';

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
        static::creating(function (self $survei): void {
            $survei->created_by ??= auth()->id();
            $survei->updated_by ??= auth()->id();
        });

        static::updating(function (self $survei): void {
            $survei->updated_by = auth()->id() ?: $survei->updated_by;
        });
    }

    public static function audienceOptions(): array
    {
        return [
            self::AUDIENCE_STUDENT => 'Murid / Orang Tua',
            self::AUDIENCE_TEACHER => 'Guru / Tendik',
        ];
    }

    public static function audienceLabel(?string $audience): string
    {
        return self::audienceOptions()[$audience] ?? ($audience ?: '-');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveiQuestion::class, 'survei_id')->orderBy('urutan');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(SurveiTarget::class, 'survei_id')->orderBy('recipient_name_snapshot');
    }

    public function submittedTargets(): HasMany
    {
        return $this->targets()->where('submission_status', SurveiTarget::STATUS_SUBMITTED);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(SurveiSubmission::class, 'survei_id')->latest('submitted_at');
    }

    public function scopeOpenForPublic(Builder $query): Builder
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

    public function hasSubmissions(): bool
    {
        return $this->submissions()->exists();
    }

    public function totalTargetsCount(): int
    {
        return (int) ($this->targets_count ?? $this->targets()->count());
    }

    public function submittedTargetsCount(): int
    {
        return (int) ($this->submitted_targets_count ?? $this->submittedTargets()->count());
    }

    public function pendingTargetsCount(): int
    {
        return max(0, $this->totalTargetsCount() - $this->submittedTargetsCount());
    }

    public function completionSummary(): string
    {
        return $this->submittedTargetsCount().' / '.$this->totalTargetsCount();
    }
}
