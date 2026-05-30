<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use Illuminate\Http\Request;

class FlashSaleController extends Controller
{
    public function index()
    {
        return response()->json(
            FlashSale::with('product')
                ->latest()
                ->get()
        );
    }

    public function active()
    {
        return response()->json(
            FlashSale::with('product')
                ->where('is_active', true)
                ->where('start_at', '<=', now())
                ->where('end_at', '>=', now())
                ->latest()
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'discount_price' => ['required', 'numeric', 'min:0'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'is_active' => ['nullable'],
        ]);

        $validated['is_active'] = filter_var(
            $request->is_active,
            FILTER_VALIDATE_BOOLEAN
        );

        $sale = FlashSale::create($validated);

        return response()->json($sale->load('product'), 201);
    }

    public function update(Request $request, $id)
    {
        $sale = FlashSale::findOrFail($id);

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'discount_price' => ['required', 'numeric', 'min:0'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'is_active' => ['nullable'],
        ]);

        $validated['is_active'] = filter_var(
            $request->is_active,
            FILTER_VALIDATE_BOOLEAN
        );

        $sale->update($validated);

        return response()->json($sale->load('product'));
    }

    public function destroy($id)
    {
        FlashSale::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Flash sale deleted',
        ]);
    }
}
