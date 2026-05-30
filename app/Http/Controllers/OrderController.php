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

        $order = DB::transaction(function () use ($validated) {
            $totalPrice = 0;

            $order = Order::create([
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'shipping_address' => $validated['shipping_address'],
                'total_price' => 0,
                'payment_status' => 'pending',
                'order_status' => 'new',
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['id']);
                $quantity = $item['quantity'];
                $subtotal = $product->price * $quantity;

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $product->price,
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
            ->where(
                'customer_phone',
                $user->phone
            )
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
            ->where(
                'customer_phone',
                $user->phone
            )
            ->findOrFail($id);

        return response()->json(
            $order
        );
    }
}
