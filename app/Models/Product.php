<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Category;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductVariantItem;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'discount_price',
        'stock',
        'weight_grams',
        'sold_count',
        'image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weight_grams' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /*
    =========================================
    CATEGORY
    =========================================
    */
    public function category()
    {
        return $this->belongsTo(
            Category::class
        );
    }

    /*
    =========================================
    REVIEWS
    =========================================
    */
    public function reviews()
    {
        return $this->hasMany(
            ProductReview::class
        );
    }

    /*
    =========================================
    FLASH SALE
    =========================================
    */
    public function flashSale()
    {
        return $this->hasOne(
            FlashSale::class
        )
            ->where(
                'is_active',
                true
            )
            ->where(
                'start_at',
                '<=',
                now()
            )
            ->where(
                'end_at',
                '>=',
                now()
            );
    }

    /*
    =========================================
    GALLERY
    =========================================
    */
    public function images()
    {
        return $this->hasMany(
            ProductImage::class
        );
    }

    /*
    =========================================
    OPTIONS
    =========================================
    */
    public function options()
    {
        return $this->hasMany(
            ProductOption::class
        );
    }

    /*
    =========================================
    VARIANTS
    =========================================
    */
    public function variants()
    {
        return $this->hasMany(
            ProductVariantItem::class
        );
    }
}
