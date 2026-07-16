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

    public function vaPayment()
    {
        return $this->belongsTo(VaPayment::class, 'va_payment_id');
    }

    /**
     * Catat 1 webhook masuk. event_type untuk Midtrans selalu 'payment'
     * (kolomnya memang didesain untuk 3 event BCA SNAP, tapi 'payment'
     * paling cocok dipakai buat notifikasi Midtrans juga).
     */
    public static function record(
        ?VaPayment $vaPayment,
        string $bank,
        array $requestPayload,
        ?array $responsePayload = null,
        int $responseHttpCode = 200,
        bool $isSuccess = true,
        ?string $errorMessage = null,
    ): self {
        return static::create([
            'va_payment_id'       => $vaPayment?->id,
            'bank'                => $bank,
            'event_type'          => 'payment',
            'request_payload'     => $requestPayload,
            'response_payload'    => $responsePayload,
            'response_http_code'  => $responseHttpCode,
            'is_success'          => $isSuccess,
            'error_message'       => $errorMessage,
            'received_at'         => now(),
        ]);
    }
}
