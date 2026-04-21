<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SppBill extends Model
{
    protected $table = 'spp_bills';

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'wa_sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'paid_amount' => 'integer',
        'amount' => 'integer',
        'wa_sent_count' => 'integer',
        'period_month' => 'integer',
        'period_year' => 'integer',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'siswa_id');
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(SppFeeType::class, 'fee_type_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SppPaymentAttachment::class, 'bill_id');
    }

    public function applyPayment(int $paidNow, ?string $notes = null): void
    {
        $paidNow = max(0, $paidNow);

        $newPaid = max(0, ((int) $this->paid_amount) + $paidNow);
        $amount = (int) $this->amount;

        $paymentStatus = 'none';
        $status = 'unpaid';

        if ($newPaid <= 0) {
            $paymentStatus = 'none';
            $status = 'unpaid';
        } elseif ($newPaid < $amount) {
            $paymentStatus = 'partial';
            $status = 'unpaid';
        } else {
            $paymentStatus = 'paid';
            $status = 'paid';
            $newPaid = $amount;
        }

        $payload = [
            'paid_amount' => $newPaid,
            'payment_status' => $paymentStatus,
            'status' => $status,
        ];

        if (is_string($notes) && trim($notes) !== '') {
            $payload['payment_notes'] = $notes;
        }

        $this->update($payload);
    }
}
