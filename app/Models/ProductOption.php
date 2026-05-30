<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProductOptionValue;

class ProductOption extends Model
{
    protected $fillable = [
        'product_id',
        'name',
    ];

    public function values()
    {
        return $this->hasMany(
            ProductOptionValue::class
        );
    }
}