<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrNew([
            'phone' => env('ADMIN_PHONE', '081234567890'),
        ]);

        $admin->fill([
            'name' => env('ADMIN_NAME', 'Lucky Pet Admin'),
            'email' => env('ADMIN_EMAIL', 'admin@luckypetmarket.com'),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        if (! $admin->exists) {
            $admin->password = Hash::make(env('ADMIN_PASSWORD', 'password'));
        }

        $admin->save();

        $this->call([
            PaymentMethodSeeder::class,
        ]);
    }
}
