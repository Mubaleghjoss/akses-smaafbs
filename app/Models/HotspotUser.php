<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotspotUser extends Model
{
    protected $table = 'hotspot_users';

    protected $fillable = [
        'username', 'password', 'profile', 'durasi', 'note', 'disabled', 'source',
    ];

    protected $casts = [
        'durasi' => 'integer',
        'disabled' => 'boolean',
    ];
}