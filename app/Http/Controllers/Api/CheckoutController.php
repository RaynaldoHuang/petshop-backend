<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        return response()->json([
            'message' => 'Checkout berhasil',
            'user' => $request->user(),
        ]);
    }
}
