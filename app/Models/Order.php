<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'customer_name',
        'customer_phone',
        'shipping_address',
        'total_price',
        'payment_status',
        'order_status',
        'stock_deducted_at',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'payment_type',
        'payment_response',
    ];

    protected $casts = [
        'payment_response' => 'array',
        'stock_deducted_at' => 'datetime',
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

    public function user()
    {
        return $this->belongsTo(User::class);
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
