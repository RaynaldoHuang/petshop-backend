<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),

            'phone' => fake()->unique()->numerify('08##########'),

            'email' => fake()->unique()->safeEmail(),

            'phone_verified_at' => now(),

            'avatar' => null,

            'is_active' => true,

            'role' => null,

            'password' => static::$password ??= Hash::make('password'),

            'remember_token' => Str::random(10),
        ];
    }
}
