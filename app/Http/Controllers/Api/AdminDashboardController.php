<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $orders = Order::query();

        return response()->json([
            'stats' => [
                'revenue' => (int) (clone $orders)
                    ->whereIn('payment_status', ['paid', 'settlement'])
                    ->sum('total_price'),
                'orders' => (clone $orders)->count(),
                'pending_orders' => (clone $orders)
                    ->whereIn('order_status', ['new', 'processed'])
                    ->count(),
                'products' => Product::count(),
                'customers' => User::whereNull('role')->count(),
                'active_payment_methods' => PaymentMethod::where('is_active', true)->count(),
            ],
            'recent_orders' => Order::with('items')
                ->latest()
                ->limit(5)
                ->get(),
            'low_stock_products' => Product::query()
                ->where('stock', '<=', 10)
                ->orderBy('stock')
                ->limit(5)
                ->get(['id', 'name', 'stock', 'is_active']),
        ]);
    }
}
