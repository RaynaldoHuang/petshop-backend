<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'code',
        'type',
        'fee',
        'fee_percentage',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'fee' => 'integer',
        'fee_percentage' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function feeBreakdown(int|float $subtotal): array
    {
        $percentageFee = (int) round(
            $subtotal * ((float) $this->fee_percentage / 100)
        );
        $adminFee = (int) $this->fee + $percentageFee;
        $tax = (int) round($adminFee * 0.11);

        return [
            'fixed_fee' => (int) $this->fee,
            'percentage_fee' => $percentageFee,
            'admin_fee' => $adminFee,
            'tax' => $tax,
            'total_fee' => $adminFee + $tax,
        ];
    }
}
