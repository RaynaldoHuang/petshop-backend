<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlashSale extends Model
{
    protected $fillable = [
        'product_id',
        'discount_price',
        'start_at',
        'end_at',
        'is_active',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}