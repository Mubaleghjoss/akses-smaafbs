<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebAuthnCredential extends Model
{
    protected $table = 'webauthn_credentials';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'transports' => 'array',
            'sign_count' => 'integer',
            'signature_counter' => 'integer',
            'user_verified' => 'boolean',
            'backup_eligible' => 'boolean',
            'backed_up' => 'boolean',
            'verified_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isVerifiedPasskey(): bool
    {
        return $this->revoked_at === null
            && filled($this->credential_public_key)
            && $this->verified_at !== null;
    }

    public function isLegacy(): bool
    {
        return blank($this->credential_public_key) || $this->verified_at === null;
    }
}
