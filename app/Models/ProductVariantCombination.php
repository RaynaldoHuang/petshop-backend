<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariantCombination extends Model
{
    protected $fillable = [
        'product_variant_item_id',
        'product_option_id',
        'product_option_value_id',
    ];
}