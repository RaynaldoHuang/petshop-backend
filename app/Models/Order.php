<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_name',
        'customer_phone',
        'shipping_address',
        'total_price',
        'payment_status',
        'order_status',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'payment_type',
        'payment_response',
    ];

    protected $casts = [
        'payment_response' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | ITEMS
    |--------------------------------------------------------------------------
    */
    public function items()
    {
        return $this->hasMany(
            OrderItem::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ALL PAYMENTS
    |--------------------------------------------------------------------------
    */
    public function payments()
    {
        return $this->hasMany(
            Payment::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LATEST PAYMENT
    |--------------------------------------------------------------------------
    */
    public function latestPayment()
    {
        return $this->hasOne(
            Payment::class
        )->latestOfMany();
    }
}
