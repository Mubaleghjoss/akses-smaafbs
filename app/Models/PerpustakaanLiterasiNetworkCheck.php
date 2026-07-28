<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerpustakaanLiterasiNetworkCheck extends Model
{
    protected $table = 'perpustakaan_literasi_network_checks';

    protected $guarded = [];

    protected $casts = [
        'dns_ok' => 'boolean',
        'tcp_ok' => 'boolean',
        'http_status' => 'integer',
        'duration_ms' => 'integer',
        'consecutive_failures' => 'integer',
        'context' => 'array',
        'checked_at' => 'datetime',
    ];
}
