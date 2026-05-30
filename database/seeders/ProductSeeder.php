<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::create([
            'name' => 'Royal Canin Kitten',
            'slug' => 'royal-canin-kitten',
            'description' => 'Makanan kucing untuk kitten',
            'price' => 120000,
            'stock' => 10,
            'image' => 'royal-canin.jpg',
            'is_active' => true,
        ]);
    }
}