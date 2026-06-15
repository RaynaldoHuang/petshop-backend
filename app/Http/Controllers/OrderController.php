<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('items')
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'shipping_address' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if (! $request->user()->isAdmin() &&
            $request->user()->phone !== $validated['customer_phone']) {
            return response()->json([
                'message' => 'Nomor telepon pesanan harus sama dengan akun yang sedang login.',
            ], 422);
        }

        $order = DB::transaction(function () use ($validated, $request) {
            $totalPrice = 0;
            $items = collect($validated['items'])
                ->groupBy('id')
                ->map(fn ($rows, $productId) => [
                    'id' => (int) $productId,
                    'quantity' => $rows->sum('quantity'),
                ]);

            $order = Order::create([
                'user_id' => $request->user()->id,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'shipping_address' => $validated['shipping_address'],
                'total_price' => 0,
                'payment_status' => 'pending',
                'order_status' => 'new',
            ]);

            foreach ($items as $item) {
                $product = Product::with('flashSale')
                    ->lockForUpdate()
                    ->findOrFail($item['id']);
                $quantity = $item['quantity'];

                abort_unless($product->is_active, 422, "{$product->name} sedang tidak aktif.");
                abort_unless(
                    $quantity <= (int) $product->stock,
                    422,
                    "Stok {$product->name} tidak mencukupi."
                );

                $unitPrice = $this->effectiveProductPrice($product);
                $subtotal = $unitPrice * $quantity;

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $unitPrice,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ]);

                $totalPrice += $subtotal;
            }

            $order->update([
                'total_price' => $totalPrice,
            ]);

            return $order->load('items');
        });

        return response()->json([
            'message' => 'Order berhasil dibuat',
            'data' => $order,
        ], 201);
    }

    private function effectiveProductPrice(Product $product): int
    {
        if ($product->flashSale) {
            return (int) $product->flashSale->discount_price;
        }

        $price = (int) $product->price;
        $discountPrice = (int) $product->discount_price;

        return $discountPrice > 0 && $discountPrice < $price
            ? $discountPrice
            : $price;
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'order_status' => ['required', 'in:new,processed,shipped,completed,cancelled'],
        ]);

        $order = Order::findOrFail($id);

        $order->update([
            'order_status' => $validated['order_status'],
        ]);

        return response()->json([
            'message' => 'Status order berhasil diperbarui',
            'data' => $order,
        ]);
    }

    public function customerOrders(Request $request)
    {
        $user = $request->user();

        $orders = Order::with([
            'items',
            'latestPayment',
        ])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere(function ($legacyQuery) use ($user) {
                        $legacyQuery
                            ->whereNull('user_id')
                            ->where('customer_phone', $user->phone);
                    });
            })
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function showCustomerOrder(
        Request $request,
        $id
    ) {

        $user = $request->user();

        $order = Order::with([
            'items',
            'payments'
        ])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere(function ($legacyQuery) use ($user) {
                        $legacyQuery
                            ->whereNull('user_id')
                            ->where('customer_phone', $user->phone);
                    });
            })
            ->findOrFail($id);

        return response()->json(
            $order
        );
    }
}
