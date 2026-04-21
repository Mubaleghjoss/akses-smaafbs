<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SppPaymentAttachment extends Model
{
    protected $table = 'spp_payment_attachments';

    const CREATED_AT = 'uploaded_at';

    const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'file_size' => 'integer',
        'amount' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(SppBill::class, 'bill_id');
    }
}
