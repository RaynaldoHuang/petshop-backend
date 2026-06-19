<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function index()
    {
        $orderSummaries = Order::query()
            ->selectRaw("
                customer_phone,
                COUNT(*) as orders_count,
                SUM(
                    CASE
                        WHEN payment_status IN ('paid', 'settlement') THEN total_price
                        ELSE 0
                    END
                ) as total_spent,
                MAX(created_at) as last_order_at
            ")
            ->groupBy('customer_phone')
            ->get()
            ->keyBy('customer_phone');

        $customers = User::latest()
            ->whereNull('role')
            ->get()
            ->map(function ($user) use ($orderSummaries) {
                $orders = $orderSummaries->get($user->phone);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'orders_count' => (int) ($orders?->orders_count ?? 0),
                    'total_spent' => (float) ($orders?->total_spent ?? 0),
                    'last_order_at' => $orders?->last_order_at,
                    'created_at' => $user->created_at,
                ];
            });

        return response()->json($customers);
    }

    public function destroy(User $customer): JsonResponse
    {
        if ($customer->isAdmin()) {
            return response()->json([
                'message' => 'Akun admin tidak dapat dihapus dari menu pelanggan.',
            ], 403);
        }

        $customer->tokens()->delete();
        $customer->trustedDevices()->delete();
        $customer->delete();

        return response()->json([
            'message' => 'Pelanggan berhasil dihapus.',
        ]);
    }
}
