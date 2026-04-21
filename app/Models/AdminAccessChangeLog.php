<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAccessChangeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_user_id',
        'actor_user_id',
        'action',
        'source',
        'template_keys',
        'before_levels',
        'after_levels',
        'changed_prefixes',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'target_user_id' => 'integer',
            'actor_user_id' => 'integer',
            'template_keys' => 'array',
            'before_levels' => 'array',
            'after_levels' => 'array',
            'changed_prefixes' => 'array',
        ];
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
