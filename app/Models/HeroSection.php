<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroSection extends Model
{
    protected $fillable = [
        'image',
        'link',
        'is_active',
        'sort_order',
    ];
}
