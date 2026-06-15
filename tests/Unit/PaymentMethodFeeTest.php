<?php

namespace Tests\Unit;

use App\Models\PaymentMethod;
use PHPUnit\Framework\TestCase;

class PaymentMethodFeeTest extends TestCase
{
    public function test_it_calculates_fixed_percentage_and_tax_fees(): void
    {
        $method = new PaymentMethod([
            'fee' => 4000,
            'fee_percentage' => 0.7,
        ]);

        $this->assertSame([
            'fixed_fee' => 4000,
            'percentage_fee' => 700,
            'admin_fee' => 4700,
            'tax' => 517,
            'total_fee' => 5217,
        ], $method->feeBreakdown(100000));
    }
}
