<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_qris_proof_and_admin_confirmation_flow(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $customer = User::factory()->create(['phone' => '081234567890']);
        $product = Product::create([
            'name' => 'Produk Manual',
            'slug' => 'produk-manual',
            'description' => 'Produk uji pembayaran manual',
            'price' => 50000,
            'stock' => 5,
            'sold_count' => 0,
            'is_active' => true,
        ]);
        $order = Order::create([
            'user_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'shipping_address' => 'Jl. Manual No. 1',
            'total_price' => 50000,
            'payment_status' => 'pending',
            'order_status' => 'new',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 50000,
            'quantity' => 1,
            'subtotal' => 50000,
        ]);
        PaymentMethod::create([
            'name' => 'QRIS',
            'code' => 'qris',
            'type' => 'qris',
            'fee' => 0,
            'fee_percentage' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($superAdmin)->post('/api/admin/payment-settings', [
            'mode' => 'manual',
            'whatsapp_number' => '0812-3456-7890',
            'manual_qris' => UploadedFile::fake()->create('qris.pdf', 100, 'application/pdf'),
        ])->assertOk()
            ->assertJsonPath('data.mode', 'manual')
            ->assertJsonPath('data.whatsapp_number', '6281234567890');

        $paymentResponse = $this->actingAs($customer)->postJson('/api/payments/create', [
            'order_id' => $order->id,
            'payment_method' => 'qris',
        ])->assertOk();
        $paymentId = $paymentResponse->json('payment_id');

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'payment_mode' => 'manual',
            'status' => 'pending',
        ]);

        $proofResponse = $this->actingAs($customer)->post("/api/payments/{$paymentId}/proof", [
            'proof' => UploadedFile::fake()->image('bukti.png'),
        ])->assertOk()
            ->assertJsonPath('status', 'awaiting_confirmation');

        $this->assertStringContainsString('wa.me/6281234567890', $proofResponse->json('whatsapp_url'));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'awaiting_confirmation',
        ]);

        $this->actingAs($superAdmin)
            ->putJson("/api/admin/payments/{$paymentId}/confirm")
            ->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'status' => 'paid']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(1, $product->fresh()->sold_count);
    }
}
