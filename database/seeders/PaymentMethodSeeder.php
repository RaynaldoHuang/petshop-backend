<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [

            [
                'name' => 'QRIS',
                'code' => 'qris',
                'type' => 'qris',
                'fee' => 1000,
                'fee_percentage' => 0,
                'sort_order' => 1,
                'is_active' => true,
            ],

            [
                'name' => 'BCA Virtual Account',
                'code' => 'bca',
                'type' => 'bank_transfer',
                'fee' => 4000,
                'fee_percentage' => 0,
                'sort_order' => 2,
                'is_active' => true,
            ],

            [
                'name' => 'Mandiri Virtual Account',
                'code' => 'mandiri',
                'type' => 'bank_transfer',
                'fee' => 4500,
                'fee_percentage' => 0,
                'sort_order' => 3,
                'is_active' => true,
            ],

            [
                'name' => 'Permata Virtual Account',
                'code' => 'permata',
                'type' => 'bank_transfer',
                'fee' => 4500,
                'fee_percentage' => 0,
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($methods as $method) {

            PaymentMethod::updateOrCreate(
                [
                    'code' => $method['code']
                ],
                $method
            );
        }
    }
}
