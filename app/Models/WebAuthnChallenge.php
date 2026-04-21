<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebAuthnChallenge extends Model
{
    protected $table = 'webauthn_challenges';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'browser_supported' => 'boolean',
            'context' => 'array',
            'challenge_expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'used_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
