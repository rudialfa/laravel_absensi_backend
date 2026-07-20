<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper singkat untuk mencatat log dari controller.
     *
     * AuditLog::record('verify_invoice', $invoice, "Invoice {$invoice->invoice_number} diverifikasi manual");
     */
    public static function record(string $action, $subject = null, ?string $description = null, array $meta = []): self
    {
        return static::create([
            'user_id'      => auth()->id(),
            'action'       => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->id,
            'description'  => $description,
            'meta'         => $meta,
            'ip_address'   => request()->ip(),
        ]);
    }
}
