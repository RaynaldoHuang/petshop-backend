<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FazpassCallbackController extends Controller
{
    public function store(Request $request)
    {
        $secret = (string) config('services.fazpass.callback_secret');

        if ($secret !== '' && $request->header('X-Fazpass-Secret') !== $secret) {
            return response()->json([
                'message' => 'Unauthorized callback.',
            ], 401);
        }

        Log::info('Fazpass OTP callback received', [
            'transaction_id' => $request->input('transaction_id'),
            'service' => $request->input('service'),
            'gateway' => $request->input('gateway'),
            'method' => $request->input('method'),
            'provider' => $request->input('provider'),
            'product' => $request->input('product'),
            'status' => $request->input('status'),
            'phone' => $request->input('phone'),
            'timestamp' => $request->input('timestamp'),
        ]);

        return response()->json([
            'message' => 'Callback received.',
        ]);
    }
}
