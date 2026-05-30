<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'phone' => '081234567890',
            'email' => 'test@example.com',
        ]);

        $this->call([
            PaymentMethodSeeder::class,
        ]);
    }
}
