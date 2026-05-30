<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProductVariantCombination;

class ProductVariantItem extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'price',
        'discount_price',
        'stock',
        'sku',
        'image',
        'is_active',
    ];

    public function combinations()
    {
        return $this->hasMany(
            ProductVariantCombination::class
        );
    }
}
