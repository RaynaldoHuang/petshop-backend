<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_payment_and_repeated_request_reuses_pending_payment(): void
    {
        [$user, $order] = $this->makeOrder();
        $this->makeQrisMethod();

        $midtrans = Mockery::mock(MidtransService::class);
        $midtrans->shouldReceive('createQrisTransaction')
            ->once()
            ->andReturn($this->qrisResponse('trx-1', 'ORDER-1-uuid'));
        $this->app->instance(MidtransService::class, $midtrans);

        $payload = ['order_id' => $order->id, 'payment_method' => 'qris'];

        $first = $this->actingAs($user)->postJson('/api/payments/create', $payload);
        $second = $this->actingAs($user)->postJson('/api/payments/create', $payload);

        $first->assertOk()->assertJson(['success' => true, 'reused' => false]);
        $second->assertOk()->assertJson([
            'success' => true,
            'payment_id' => $first->json('payment_id'),
            'reused' => true,
        ]);

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'id' => $first->json('payment_id'),
            'gross_amount' => 100210,
            'admin_fee_amount' => 1090,
            'admin_fee_tax' => 120,
        ]);
    }

    public function test_valid_webhook_marks_payment_paid_and_deducts_stock_only_once(): void
    {
        [, $order, $product] = $this->makeOrder(quantity: 2);
        $payment = $this->makePayment($order);
        config(['midtrans.server_key' => 'test-server-key']);

        $payload = $this->notificationPayload($payment, 'settlement');

        $this->postJson('/api/midtrans/notification', $payload)
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'paid']);
        $this->postJson('/api/midtrans/notification', $payload)
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'paid']);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
        ]);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(2, $product->fresh()->sold_count);
    }

    public function test_webhook_rejects_invalid_signature_and_amount(): void
    {
        [, $order] = $this->makeOrder();
        $payment = $this->makePayment($order);
        config(['midtrans.server_key' => 'test-server-key']);

        $this->postJson('/api/midtrans/notification', [
            ...$this->notificationPayload($payment, 'settlement'),
            'signature_key' => 'invalid',
        ])->assertForbidden();

        $wrongAmount = $this->notificationPayload($payment, 'settlement', '999.00');
        $this->postJson('/api/midtrans/notification', $wrongAmount)
            ->assertUnprocessable();

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_paid_order_cannot_create_or_retry_payment(): void
    {
        [$user, $order] = $this->makeOrder();
        $this->makeQrisMethod();
        $this->makePayment($order, status: 'paid');
        $order->update(['payment_status' => 'paid']);

        $midtrans = Mockery::mock(MidtransService::class);
        $midtrans->shouldNotReceive('createQrisTransaction');
        $this->app->instance(MidtransService::class, $midtrans);

        $this->actingAs($user)->postJson('/api/payments/create', [
            'order_id' => $order->id,
            'payment_method' => 'qris',
        ])->assertUnprocessable();

        $this->actingAs($user)
            ->postJson("/api/payments/retry/{$order->id}")
            ->assertUnprocessable();
    }

    public function test_order_uses_authoritative_discount_price_and_rejects_insufficient_stock(): void
    {
        $user = User::factory()->create(['phone' => '081234567890']);
        $product = Product::create([
            'name' => 'Makanan Kucing',
            'slug' => 'makanan-kucing',
            'description' => 'Produk uji',
            'price' => 100000,
            'discount_price' => 75000,
            'stock' => 3,
            'sold_count' => 0,
            'is_active' => true,
        ]);

        $payload = [
            'customer_name' => $user->name,
            'customer_phone' => $user->phone,
            'shipping_address' => 'Jl. Testing No. 1',
            'items' => [
                ['id' => $product->id, 'quantity' => 2],
            ],
        ];

        $this->actingAs($user)->postJson('/api/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.total_price', 150000)
            ->assertJsonPath('data.items.0.price', 75000);

        $payload['items'][0]['quantity'] = 4;
        $this->actingAs($user)->postJson('/api/orders', $payload)
            ->assertUnprocessable();
    }

    private function makeOrder(int $quantity = 1): array
    {
        $user = User::factory()->create(['phone' => '081234567890']);
        $product = Product::create([
            'name' => 'Whiskas Test',
            'slug' => 'whiskas-test',
            'description' => 'Produk uji',
            'price' => 99000,
            'stock' => 10,
            'sold_count' => 0,
            'is_active' => true,
        ]);
        $order = Order::create([
            'customer_name' => $user->name,
            'customer_phone' => $user->phone,
            'shipping_address' => 'Jl. Testing No. 1',
            'total_price' => 99000 * $quantity,
            'payment_status' => 'pending',
            'order_status' => 'new',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 99000,
            'quantity' => $quantity,
            'subtotal' => 99000 * $quantity,
        ]);

        return [$user, $order, $product];
    }

    private function makeQrisMethod(): PaymentMethod
    {
        return PaymentMethod::create([
            'name' => 'QRIS',
            'code' => 'qris',
            'type' => 'qris',
            'fee' => 100,
            'fee_percentage' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function makePayment(Order $order, string $status = 'pending'): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'transaction_id' => 'trx-'.$order->id,
            'midtrans_order_id' => 'ORDER-'.$order->id.'-test',
            'payment_method' => 'qris',
            'type' => 'qris',
            'gross_amount' => (int) $order->total_price,
            'admin_fee_amount' => 0,
            'admin_fee_tax' => 0,
            'qr_url' => 'https://example.test/qr',
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
            'expires_at' => now()->addHour(),
        ]);
    }

    private function qrisResponse(string $transactionId, string $orderId): object
    {
        return (object) [
            'transaction_id' => $transactionId,
            'order_id' => $orderId,
            'actions' => [(object) ['url' => 'https://example.test/qr']],
            'expiry_time' => now()->addHour()->toDateTimeString(),
        ];
    }

    private function notificationPayload(
        Payment $payment,
        string $status,
        ?string $grossAmount = null
    ): array {
        $grossAmount ??= number_format((int) $payment->gross_amount, 2, '.', '');
        $statusCode = '200';

        return [
            'order_id' => $payment->midtrans_order_id,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => $status,
            'signature_key' => hash(
                'sha512',
                $payment->midtrans_order_id
                    .$statusCode
                    .$grossAmount
                    .config('midtrans.server_key')
            ),
        ];
    }
}
