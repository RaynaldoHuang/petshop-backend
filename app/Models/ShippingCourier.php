<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingCourier extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
