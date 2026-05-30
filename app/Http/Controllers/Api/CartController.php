<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /*
    =========================================
    GET CART
    =========================================
    */
    public function index(Request $request)
    {
        return response()->json([
            'message' => 'Cart user',
            'user' => $request->user(),
        ]);
    }

    /*
    =========================================
    ADD TO CART
    =========================================
    */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required',
            'quantity' => 'required|integer|min:1',
        ]);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan ke cart',
            'data' => $validated,
        ]);
    }

    /*
    =========================================
    REMOVE CART
    =========================================
    */
    public function destroy($id)
    {
        return response()->json([
            'message' => 'Produk dihapus dari cart',
        ]);
    }
}
