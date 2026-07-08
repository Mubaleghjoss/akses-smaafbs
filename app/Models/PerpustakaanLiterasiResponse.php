<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PerpustakaanLiterasiResponse extends Model
{
    use SoftDeletes;

    public const AI_STATUS_NOT_CHECKED = 'not_checked';

    protected $table = 'perpustakaan_literasi_responses';

    protected $guarded = [];

    protected $casts = [
        'submitted_at' => 'datetime',
        'last_edited_at' => 'datetime',
        'ai_score' => 'float',
        'ai_metadata' => 'array',
        'tab_switch_count' => 'integer',
        'app_hidden_count' => 'integer',
        'page_leave_attempt_count' => 'integer',
        'last_integrity_event_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $response): void {
            if (blank($response->edit_code)) {
                $response->edit_code = static::generateEditCode();
            }

            $response->ai_detection_status ??= self::AI_STATUS_NOT_CHECKED;
        });

        static::deleting(function (self $response): void {
            if ($response->isForceDeleting()) {
                return;
            }

            $response->answers()->get()->each->delete();
            $response->laterSimilarityMatches()->get()->each->delete();
            $response->matchedSimilarityMatches()->get()->each->delete();
        });

        static::restoring(function (self $response): void {
            $response->answers()->withTrashed()->get()->each->restore();
            $response->laterSimilarityMatches()->withTrashed()->get()->each->restore();
            $response->matchedSimilarityMatches()->withTrashed()->get()->each->restore();
        });
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiMaterial::class, 'material_id')->withTrashed();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'data_siswa_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PerpustakaanLiterasiAnswer::class, 'response_id');
    }

    public function laterSimilarityMatches(): HasMany
    {
        return $this->hasMany(PerpustakaanLiterasiSimilarityMatch::class, 'later_response_id');
    }

    public function matchedSimilarityMatches(): HasMany
    {
        return $this->hasMany(PerpustakaanLiterasiSimilarityMatch::class, 'matched_response_id');
    }

    public function shortEditCode(): string
    {
        return Str::afterLast((string) $this->edit_code, '-');
    }

    public function editUrl(): string
    {
        return route('library.literacy.edit', $this->shortEditCode());
    }

    /**
     * @param  array{tab_switch_count?: int, app_hidden_count?: int, page_leave_attempt_count?: int}  $counts
     */
    public function addIntegrityCounts(array $counts): void
    {
        $tabSwitches = max(0, (int) ($counts['tab_switch_count'] ?? 0));
        $appHidden = max(0, (int) ($counts['app_hidden_count'] ?? 0));
        $leaveAttempts = max(0, (int) ($counts['page_leave_attempt_count'] ?? 0));

        if ($tabSwitches + $appHidden + $leaveAttempts <= 0) {
            return;
        }

        $this->forceFill([
            'tab_switch_count' => (int) ($this->tab_switch_count ?? 0) + $tabSwitches,
            'app_hidden_count' => (int) ($this->app_hidden_count ?? 0) + $appHidden,
            'page_leave_attempt_count' => (int) ($this->page_leave_attempt_count ?? 0) + $leaveAttempts,
            'last_integrity_event_at' => now(),
        ])->save();
    }

    public static function generateEditCode(): string
    {
        do {
            $shortCode = Str::upper(Str::random(6));
            $code = 'LHP-'.now()->format('ymd').'-'.$shortCode;
        } while (
            static::query()->where('edit_code', $code)->exists()
            || static::query()->where('edit_code', 'like', '%-'.$shortCode)->exists()
        );

        return $code;
    }
}
