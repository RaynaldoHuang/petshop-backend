<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\RajaOngkirService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RajaOngkirShippingWeightTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipping_cost_uses_product_weight_in_grams(): void
    {
        $food = Product::create([
            'name' => 'Dry Food 1.2kg',
            'slug' => 'dry-food-12kg',
            'description' => 'Produk uji',
            'price' => 100000,
            'stock' => 10,
            'weight_grams' => 1200,
            'is_active' => true,
        ]);
        $snack = Product::create([
            'name' => 'Snack 250g',
            'slug' => 'snack-250g',
            'description' => 'Produk uji',
            'price' => 25000,
            'stock' => 10,
            'weight_grams' => 250,
            'is_active' => true,
        ]);

        $rajaOngkir = Mockery::mock(RajaOngkirService::class);
        $rajaOngkir->shouldReceive('activeCouriers')
            ->once()
            ->andReturn([
                ['code' => 'jne', 'name' => 'JNE'],
            ]);
        $rajaOngkir->shouldReceive('settings')
            ->once()
            ->andReturn([
                'default_item_weight' => 1000,
            ]);
        $rajaOngkir->shouldReceive('cost')
            ->once()
            ->with('12345', 2650, 'jne')
            ->andReturn([
                [
                    'code' => 'jne',
                    'costs' => [],
                ],
            ]);

        $this->app->instance(RajaOngkirService::class, $rajaOngkir);

        $this->postJson('/api/shipping/costs', [
            'destination' => '12345',
            'courier' => 'jne',
            'items' => [
                ['id' => $food->id, 'quantity' => 2],
                ['id' => $snack->id, 'quantity' => 1],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('weight', 2650);
    }
}
