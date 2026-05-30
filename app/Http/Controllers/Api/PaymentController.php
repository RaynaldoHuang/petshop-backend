<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;

use App\Services\MidtransService;

class PaymentController extends Controller
{
    /*
    =========================================
    CREATE PAYMENT
    =========================================
    */
    public function createPayment(
        Request $request,
        MidtransService $midtrans
    ) {

        $request->validate([
            'order_id' => 'required',
            'payment_method' => 'required',
        ]);

        /*
        =========================================
        ORDER
        =========================================
        */
        $order = Order::findOrFail(
            $request->order_id
        );

        /*
        =========================================
        PAYMENT METHOD
        =========================================
        */
        $method = PaymentMethod::where(
            'code',
            $request->payment_method
        )->firstOrFail();

        /*
        =========================================
        TOTAL
        =========================================
        */
        $grossAmount =
            (int) $order->total_price +
            (int) $method->fee;

        /*
        =========================================
        MIDTRANS ORDER ID
        =========================================
        */
        $midtransOrderId =
            'ORDER-' .
            $order->id .
            '-' .
            time();

        /*
        =========================================
        PAYLOAD
        =========================================
        */
        $payload = [
            'order_id' => $midtransOrderId,

            'gross_amount' => $grossAmount,
        ];

        /*
        =========================================
        QRIS
        =========================================
        */
        if ($method->type === 'qris') {

            $response =
                $midtrans->createQrisTransaction(
                    $payload
                );

            $payment =
                Payment::create([
                    'order_id' =>
                    $order->id,

                    'transaction_id' =>
                    $response->transaction_id,

                    'midtrans_order_id' =>
                    $response->order_id,

                    'payment_method' =>
                    $method->code,

                    'type' =>
                    'qris',

                    'gross_amount' =>
                    $grossAmount,

                    'qr_url' =>
                    $response->actions[0]->url ?? null,

                    'expires_at' =>
                    $response->expiry_time ?? null,

                    'status' =>
                    'pending',
                ]);

            return response()->json([
                'success' => true,

                'payment_id' =>
                $payment->id,
            ]);
        }

        /*
        =========================================
        BANK TRANSFER
        =========================================
        */
        $response =
            $midtrans->createBankTransfer(
                $payload,
                $method->code
            );

        /*
=========================================
VA DATA
=========================================
*/
        $vaNumber = null;
        $bank = null;

        if (
            isset($response->va_numbers[0])
        ) {

            $vaItem =
                json_decode(
                    json_encode(
                        $response->va_numbers[0]
                    ),
                    true
                );

            $vaNumber =
                $vaItem['va_number'] ?? null;

            $bank =
                $vaItem['bank'] ?? null;
        }
        /*
        =========================================
        CREATE PAYMENT
        =========================================
        */
        $payment =
            Payment::create([
                'order_id' =>
                $order->id,

                'transaction_id' =>
                $response->transaction_id,

                'midtrans_order_id' =>
                $response->order_id,

                'payment_method' =>
                $method->code,

                'type' =>
                'bank_transfer',

                'gross_amount' =>
                $grossAmount,

                'va_number' =>
                $vaNumber,

                'bank' =>
                $bank,

                'expires_at' =>
                $response->expiry_time ?? null,

                'status' =>
                'pending',
            ]);

        return response()->json([
            'success' => true,

            'payment_id' =>
            $payment->id,
        ]);
    }

    /*
    =========================================
    SHOW PAYMENT
    =========================================
    */
    public function show($id)
    {
        $payment =
            Payment::findOrFail($id);

        return response()->json([
            'id' =>
            $payment->id,

            'type' =>
            $payment->type,

            'gross_amount' =>
            $payment->gross_amount,

            'qr_url' =>
            $payment->qr_url,

            'va_number' =>
            $payment->va_number,

            'bank' =>
            $payment->bank,

            'expires_at' =>
            $payment->expires_at,

            'status' =>
            $payment->status,
        ]);
    }

    /*
    =========================================
    CHECK STATUS
    =========================================
    */
    public function checkStatus(
        $id,
        MidtransService $midtrans
    ) {

        $payment =
            Payment::findOrFail($id);

        /*
        =========================================
        MIDTRANS STATUS
        =========================================
        */
        $response =
            $midtrans->getTransactionStatus(
                $payment->midtrans_order_id
            );

        /*
        =========================================
        SUCCESS
        =========================================
        */
        if (
            in_array(
                $response->transaction_status,
                ['settlement', 'capture']
            )
        ) {

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $payment->order->update([
                'payment_status' => 'paid',
            ]);
        }

        /*
        =========================================
        EXPIRED
        =========================================
        */
        if (
            $response->transaction_status === 'expire'
        ) {

            $payment->update([
                'status' => 'expired',
            ]);

            $payment->order->update([
                'payment_status' => 'expired',
            ]);
        }



        /*
=========================================
FAILED
=========================================
*/
        if (
            in_array(
                $response->transaction_status,
                ['cancel', 'deny']
            )
        ) {

            $payment->update([
                'status' => 'failed',
            ]);

            $payment->order->update([
                'payment_status' => 'failed',
            ]);
        }

        return response()->json([
            'status' =>
            $payment->fresh()->status,
        ]);
    }

    public function retryPayment(
        Order $order,
        MidtransService $midtrans
    ) {

        $lastPayment =
            $order->payments()
            ->latest()
            ->first();

        if (!$lastPayment) {

            return response()->json([
                'message' =>
                'Payment tidak ditemukan'
            ], 404);
        }

        $payload = [
            'order_id' =>
            'ORDER-' .
                $order->id .
                '-' .
                time(),

            'gross_amount' =>
            $lastPayment->gross_amount,
        ];

        if (
            $lastPayment->type === 'qris'
        ) {

            $response =
                $midtrans->createQrisTransaction(
                    $payload
                );

            $payment =
                Payment::create([
                    'order_id' =>
                    $order->id,

                    'transaction_id' =>
                    $response->transaction_id,

                    'midtrans_order_id' =>
                    $response->order_id,

                    'payment_method' =>
                    $lastPayment->payment_method,

                    'type' =>
                    'qris',

                    'gross_amount' =>
                    $lastPayment->gross_amount,

                    'qr_url' =>
                    $response->actions[0]->url,

                    'expires_at' =>
                    $response->expiry_time,

                    'status' =>
                    'pending',
                ]);
        } else {

            $response =
                $midtrans->createBankTransfer(
                    $payload,
                    $lastPayment->payment_method
                );

            $payment =
                Payment::create([
                    'order_id' =>
                    $order->id,

                    'transaction_id' =>
                    $response->transaction_id,

                    'midtrans_order_id' =>
                    $response->order_id,

                    'payment_method' =>
                    $lastPayment->payment_method,

                    'type' =>
                    'bank_transfer',

                    'gross_amount' =>
                    $lastPayment->gross_amount,

                    'va_number' =>
                    $response->va_numbers[0]->va_number ?? null,

                    'bank' =>
                    $response->va_numbers[0]->bank ?? null,

                    'expires_at' =>
                    $response->expiry_time,

                    'status' =>
                    'pending',
                ]);
        }

        return response()->json([
            'success' => true,
            'payment_id' => $payment->id,
        ]);
    }
}
