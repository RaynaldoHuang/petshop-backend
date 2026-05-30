<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'thumbnail',
        'is_published',
        'published_at',
        'meta_title',
        'meta_description',
        'category',
        'tags',
        'reading_time',
    ];
}
