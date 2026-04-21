<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class SurveiTarget extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    protected $table = 'survei_targets';

    protected $guarded = [];

    protected $casts = [
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $target): void {
            if (blank($target->access_token)) {
                $target->access_token = (string) Str::uuid();
            }
        });
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Belum mengisi',
            self::STATUS_SUBMITTED => 'Sudah mengisi',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return self::statusOptions()[$status] ?? ($status ?: '-');
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_SUBMITTED => 'success',
            self::STATUS_PENDING => 'warning',
            default => 'gray',
        };
    }

    public function survei(): BelongsTo
    {
        return $this->belongsTo(Survei::class, 'survei_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'data_siswa_id');
    }

    public function guruTendik(): BelongsTo
    {
        return $this->belongsTo(GuruTendik::class, 'guru_tendik_id');
    }

    public function submission(): HasOne
    {
        return $this->hasOne(SurveiSubmission::class, 'survei_target_id');
    }

    public function recipientName(): string
    {
        return trim((string) $this->recipient_name_snapshot) !== ''
            ? (string) $this->recipient_name_snapshot
            : ($this->student?->nama ?? $this->guruTendik?->nama ?? '-');
    }

    public function recipientContext(): ?string
    {
        $snapshot = trim((string) $this->recipient_context_snapshot);

        if ($snapshot !== '') {
            return $snapshot;
        }

        return $this->student?->rombel_saat_ini ?: $this->guruTendik?->jenis_ptk;
    }

    public function publicUrl(): string
    {
        return route('survei.public.show', $this->access_token);
    }

    public function whatsappUrl(): ?string
    {
        $number = preg_replace('/[^0-9]/', '', (string) $this->whatsapp_number);

        if ($number === null || $number === '') {
            return null;
        }

        if (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        }

        $message = trim(implode("\n", [
            'Assalamu\'alaikum.',
            'Mohon bantu isi survei "'.$this->survei->title.'" untuk '.$this->recipientName().'.',
            $this->publicUrl(),
        ]));

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }

    public function markSubmitted(?string $submittedAt = null): void
    {
        $this->forceFill([
            'submission_status' => self::STATUS_SUBMITTED,
            'submitted_at' => $submittedAt ?: now(),
        ])->saveQuietly();
    }
}
