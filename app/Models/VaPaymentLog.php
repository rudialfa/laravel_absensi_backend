<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VaPaymentLog extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'request_payload'  => 'array',
        'response_payload' => 'array',
        'is_success'       => 'boolean',
        'received_at'      => 'datetime',
    ];

    // ============================================================
    // RELATIONS
    // ============================================================

    /**
     * Log ini terkait dengan satu VA payment.
     * VaPaymentLog → belongsTo → VaPayment
     */
    public function vaPayment()
    {
        return $this->belongsTo(VaPayment::class, 'va_payment_id');
    }
}
