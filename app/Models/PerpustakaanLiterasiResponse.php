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

    public const SIMILARITY_STATUS_PENDING = 'pending';

    public const SIMILARITY_STATUS_PROCESSING = 'processing';

    public const SIMILARITY_STATUS_COMPLETED = 'completed';

    public const SIMILARITY_STATUS_FAILED = 'failed';

    public const SUBMISSION_DELIVERY_DIRECT = 'OK-LANGSUNG';

    public const SUBMISSION_DELIVERY_QUEUED = 'Q-ANTRE';

    public const SUBMISSION_DELIVERY_RETRY_429 = 'R-429';

    public const SUBMISSION_DELIVERY_RETRY_503 = 'R-503';

    public const SUBMISSION_DELIVERY_RETRY_OTHER = 'R-RETRY';

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
        'similarity_analysis_version' => 'integer',
        'similarity_analysis_queued_at' => 'datetime',
        'similarity_analyzed_at' => 'datetime',
        'submission_queue_wait_seconds' => 'integer',
        'submission_retry_statuses' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $response): void {
            if (blank($response->edit_code)) {
                $material = $response->relationLoaded('material')
                    ? $response->material
                    : PerpustakaanLiterasiMaterial::query()
                        ->select(['id', 'program_category'])
                        ->find($response->material_id);

                $response->edit_code = static::generateEditCode($material?->program_category);
            }

            $response->ai_detection_status ??= self::AI_STATUS_NOT_CHECKED;
        });

        static::deleting(function (self $response): void {
            if ($response->isForceDeleting()) {
                $answerIds = $response->answers()
                    ->withTrashed()
                    ->pluck('id')
                    ->all();

                PerpustakaanLiterasiSimilarityMatch::withTrashed()
                    ->where(function ($query) use ($response, $answerIds): void {
                        $query
                            ->where('later_response_id', $response->getKey())
                            ->orWhere('matched_response_id', $response->getKey());

                        if ($answerIds !== []) {
                            $query
                                ->orWhereIn('later_answer_id', $answerIds)
                                ->orWhereIn('matched_answer_id', $answerIds);
                        }
                    })
                    ->get()
                    ->each
                    ->forceDelete();

                $response->answers()
                    ->withTrashed()
                    ->get()
                    ->each
                    ->forceDelete();

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

    public function submissionDeliveryLabel(): string
    {
        return match ($this->submission_delivery_code) {
            self::SUBMISSION_DELIVERY_DIRECT => 'Submit langsung',
            self::SUBMISSION_DELIVERY_QUEUED => 'Sempat mengantre',
            self::SUBMISSION_DELIVERY_RETRY_429 => 'Pulih setelah 429',
            self::SUBMISSION_DELIVERY_RETRY_503 => 'Pulih setelah 503',
            self::SUBMISSION_DELIVERY_RETRY_OTHER => 'Pulih setelah gangguan',
            default => 'Data lama',
        };
    }

    public function submissionDeliveryDescription(): string
    {
        $parts = [];

        if ((int) $this->submission_queue_wait_seconds > 0) {
            $parts[] = 'Antre '.number_format((int) $this->submission_queue_wait_seconds, 0, ',', '.').' detik';
        }

        $retryStatuses = collect($this->submission_retry_statuses ?? [])
            ->map(fn (mixed $status): string => (string) $status)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($retryStatuses !== []) {
            $parts[] = 'Retry '.implode(', ', $retryStatuses);
        }

        return $parts !== [] ? implode(' | ', $parts) : $this->submissionDeliveryLabel();
    }

    public function submissionDeliveryColor(): string
    {
        return match ($this->submission_delivery_code) {
            self::SUBMISSION_DELIVERY_DIRECT => 'success',
            self::SUBMISSION_DELIVERY_QUEUED => 'info',
            self::SUBMISSION_DELIVERY_RETRY_429 => 'warning',
            self::SUBMISSION_DELIVERY_RETRY_503 => 'danger',
            self::SUBMISSION_DELIVERY_RETRY_OTHER => 'warning',
            default => 'gray',
        };
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

    public static function generateEditCode(?string $programCategory = null): string
    {
        $prefix = PerpustakaanLiterasiMaterial::editCodePrefixForCategory($programCategory);

        do {
            $shortCode = Str::upper(Str::random(6));
            $code = $prefix.'-'.now()->format('ymd').'-'.$shortCode;
        } while (
            static::query()->where('edit_code', $code)->exists()
            || static::query()->where('edit_code', 'like', '%-'.$shortCode)->exists()
        );

        return $code;
    }
}
