<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicConnectivityEvent extends Model
{
    public const TYPE_SESSION_SEEN = 'session_seen';

    public const TYPE_NETWORK_ERROR = 'navigation_network_error';

    public const TYPE_SERVER_UNAVAILABLE = 'navigation_server_unavailable';

    protected $guarded = [];

    protected $casts = [
        'http_status' => 'integer',
        'occurred_at' => 'datetime',
        'recovered_at' => 'datetime',
        'offline_duration_seconds' => 'integer',
    ];
}
