<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RajaOngkirSetting extends Model
{
    protected $fillable = [
        'origin_destination_id',
        'origin_province',
        'origin_city',
        'origin_district',
        'origin_subdistrict',
        'origin_zip_code',
        'default_item_weight',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_item_weight' => 'integer',
    ];
}
