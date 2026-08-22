<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'transaction_id',
        'midtrans_order_id',
        'payment_method',
        'type',
        'payment_mode',
        'gross_amount',
        'admin_fee_amount',
        'admin_fee_tax',
        'qr_url',
        'va_number',
        'bank',
        'proof_path',
        'proof_original_name',
        'proof_submitted_at',
        'status',
        'paid_at',
        'expires_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'proof_submitted_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | ORDER
    |--------------------------------------------------------------------------
    */
    public function order(): BelongsTo
    {
        return $this->belongsTo(
            Order::class
        );
    }
}
