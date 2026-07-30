<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportShareLink extends Model
{
    use HasFactory;

    protected $table = 'assessment_report_share_links';

    protected $fillable = [
        'assessment_report_snapshot_id',
        'token_hash',
        'expires_at',
        'revoked_at',
        'created_by',
        'last_accessed_at',
        'download_count',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_by' => 'integer',
            'last_accessed_at' => 'datetime',
            'download_count' => 'integer',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ReportSnapshot::class, 'assessment_report_snapshot_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
