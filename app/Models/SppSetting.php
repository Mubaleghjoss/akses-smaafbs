<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SppSetting extends Model
{
    protected $table = 'spp_settings';

    public $incrementing = false;

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
