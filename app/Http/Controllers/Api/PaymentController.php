<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class PaymentController extends Controller
{
    public function createPayment(Request $request, MidtransService $midtrans): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'payment_method' => [
                'required',
                'string',
                Rule::exists('payment_methods', 'code')->where('is_active', true),
            ],
        ]);

        $order = Order::findOrFail($validated['order_id']);
        $this->ensureOrderAccess($request, $order);

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Pesanan ini sudah dibayar.'], 422);
        }

        if ($payment = $this->reusablePendingPayment($order)) {
            return response()->json([
                'success' => true,
                'payment_id' => $payment->id,
                'reused' => true,
            ]);
        }

        $method = PaymentMethod::where('code', $validated['payment_method'])
            ->where('is_active', true)
            ->firstOrFail();
        $fee = $method->feeBreakdown((int) $order->total_price);
        $grossAmount = (int) $order->total_price + $fee['total_fee'];
        $setting = PaymentSetting::current();

        if ($setting->isManual()) {
            abort_unless($method->code === 'qris', 422, 'Mode manual hanya mendukung pembayaran QRIS.');
            abort_unless($setting->manual_qris_path, 422, 'QRIS manual belum dikonfigurasi oleh admin.');

            $payment = Payment::create([
                'order_id' => $order->id,
                'transaction_id' => 'MANUAL-'.Str::uuid(),
                'midtrans_order_id' => 'MANUAL-ORDER-'.$order->id.'-'.Str::uuid(),
                'payment_method' => $method->code,
                'type' => 'qris',
                'payment_mode' => 'manual',
                'gross_amount' => $grossAmount,
                'admin_fee_amount' => $fee['admin_fee'],
                'admin_fee_tax' => $fee['tax'],
                'qr_url' => Storage::disk('public')->url($setting->manual_qris_path),
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'payment_id' => $payment->id,
                'reused' => false,
            ]);
        }

        try {
            $payment = $this->charge(
                $midtrans,
                $order,
                $method->code,
                $method->type,
                $grossAmount,
                $fee['admin_fee'],
                $fee['tax'],
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Midtrans belum dapat membuat transaksi. Silakan coba kembali.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'payment_id' => $payment->id,
            'reused' => false,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $payment = Payment::with('order')->findOrFail($id);
        $this->ensureOrderAccess($request, $payment->order);

        return response()->json([
            'id' => $payment->id,
            'type' => $payment->type,
            'payment_mode' => $payment->payment_mode,
            'payment_method' => $payment->payment_method,
            'gross_amount' => $payment->gross_amount,
            'admin_fee_amount' => $payment->admin_fee_amount,
            'admin_fee_tax' => $payment->admin_fee_tax,
            'qr_url' => $payment->qr_url,
            'va_number' => $payment->va_number,
            'bank' => $payment->bank,
            'expires_at' => $payment->expires_at,
            'status' => $payment->status,
            'qr_mime' => $payment->payment_mode === 'manual'
                ? PaymentSetting::current()->manual_qris_mime
                : null,
            'proof_submitted_at' => $payment->proof_submitted_at,
            'proof_original_name' => $payment->proof_original_name,
            'whatsapp_url' => $payment->payment_mode === 'manual'
                ? $this->whatsappUrl($payment)
                : null,
        ]);
    }

    public function checkStatus(
        Request $request,
        int $id,
        MidtransService $midtrans
    ): JsonResponse {
        $payment = Payment::with('order')->findOrFail($id);
        $this->ensureOrderAccess($request, $payment->order);

        if ($payment->payment_mode === 'manual') {
            return response()->json(['status' => $payment->status]);
        }

        try {
            $response = $midtrans->getTransactionStatus($payment->midtrans_order_id);
            $payment = $this->applyMidtransStatus(
                $payment,
                $response->transaction_status,
                $response->fraud_status ?? null,
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Status pembayaran belum dapat diperiksa.',
                'status' => $payment->status,
            ], 502);
        }

        return response()->json(['status' => $payment->status]);
    }

    public function notification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string'],
            'status_code' => ['required', 'string'],
            'gross_amount' => ['required'],
            'signature_key' => ['required', 'string'],
            'transaction_status' => ['required', 'string'],
            'fraud_status' => ['nullable', 'string'],
        ]);

        $expectedSignature = hash(
            'sha512',
            $validated['order_id']
                .$validated['status_code']
                .$validated['gross_amount']
                .config('midtrans.server_key')
        );

        abort_unless(
            hash_equals($expectedSignature, $validated['signature_key']),
            403,
            'Signature Midtrans tidak valid.'
        );

        $payment = Payment::where('midtrans_order_id', $validated['order_id'])->firstOrFail();

        abort_unless(
            (int) round((float) $validated['gross_amount']) === (int) $payment->gross_amount,
            422,
            'Nominal pembayaran tidak sesuai.'
        );

        $payment = $this->applyMidtransStatus(
            $payment,
            $validated['transaction_status'],
            $validated['fraud_status'] ?? null,
        );

        return response()->json([
            'success' => true,
            'status' => $payment->status,
        ]);
    }

    public function retryPayment(
        Request $request,
        Order $order,
        MidtransService $midtrans
    ): JsonResponse {
        $this->ensureOrderAccess($request, $order);

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Pesanan ini sudah dibayar.'], 422);
        }

        if ($payment = $this->reusablePendingPayment($order)) {
            return response()->json([
                'success' => true,
                'payment_id' => $payment->id,
                'reused' => true,
            ]);
        }

        $lastPayment = $order->payments()->latest()->first();

        if (! $lastPayment) {
            return response()->json(['message' => 'Pembayaran tidak ditemukan.'], 404);
        }

        if ($lastPayment->payment_mode === 'manual') {
            return response()->json([
                'success' => true,
                'payment_id' => $lastPayment->id,
                'reused' => true,
            ]);
        }

        try {
            $payment = $this->charge(
                $midtrans,
                $order,
                $lastPayment->payment_method,
                $lastPayment->type,
                (int) $lastPayment->gross_amount,
                (int) $lastPayment->admin_fee_amount,
                (int) $lastPayment->admin_fee_tax,
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Midtrans belum dapat membuat transaksi baru.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'payment_id' => $payment->id,
            'reused' => false,
        ]);
    }

    private function charge(
        MidtransService $midtrans,
        Order $order,
        string $method,
        string $type,
        int $grossAmount,
        int $adminFee,
        int $adminFeeTax,
    ): Payment {
        $payload = [
            'order_id' => 'ORDER-'.$order->id.'-'.Str::uuid(),
            'gross_amount' => $grossAmount,
        ];

        if ($type === 'qris') {
            $response = $midtrans->createQrisTransaction($payload);
            $data = [
                'type' => 'qris',
                'qr_url' => $response->actions[0]->url ?? null,
            ];
        } elseif ($method === 'mandiri') {
            $response = $midtrans->createMandiriBill($payload);
            $data = [
                'type' => 'bank_transfer',
                'va_number' => $response->bill_key ?? null,
                'bank' => 'mandiri',
            ];
        } else {
            $response = $midtrans->createBankTransfer($payload, $method);
            $data = [
                'type' => 'bank_transfer',
                'va_number' => $response->va_numbers[0]->va_number ?? null,
                'bank' => $response->va_numbers[0]->bank ?? $method,
            ];
        }

        return Payment::create([
            'order_id' => $order->id,
            'transaction_id' => $response->transaction_id,
            'midtrans_order_id' => $response->order_id,
            'payment_method' => $method,
            'payment_mode' => 'realtime',
            'gross_amount' => $grossAmount,
            'admin_fee_amount' => $adminFee,
            'admin_fee_tax' => $adminFeeTax,
            'expires_at' => $response->expiry_time ?? null,
            'status' => 'pending',
            ...$data,
        ]);
    }

    private function reusablePendingPayment(Order $order): ?Payment
    {
        $payment = $order->payments()->latest()->first();

        if (! $payment || ! in_array($payment->status, ['pending', 'awaiting_confirmation'], true)) {
            return null;
        }

        if ($payment->expires_at && $payment->expires_at->isPast()) {
            $payment->update(['status' => 'expired']);

            if ($order->payment_status !== 'paid') {
                $order->update(['payment_status' => 'expired']);
            }

            return null;
        }

        return $payment;
    }

    public function submitProof(Request $request, Payment $payment): JsonResponse
    {
        $payment->load('order');
        $this->ensureOrderAccess($request, $payment->order);

        abort_unless($payment->payment_mode === 'manual', 422, 'Bukti bayar hanya digunakan untuk pembayaran manual.');
        abort_if($payment->status === 'paid', 422, 'Pembayaran ini sudah dikonfirmasi lunas.');

        $validated = $request->validate([
            'proof' => [
                'required',
                'file',
                'max:5120',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
            ],
        ]);

        if ($payment->proof_path) {
            Storage::disk('local')->delete($payment->proof_path);
        }

        $file = $validated['proof'];
        $path = $file->store('payment-proofs', 'local');

        $payment->update([
            'proof_path' => $path,
            'proof_original_name' => $file->getClientOriginalName(),
            'proof_submitted_at' => now(),
            'status' => 'awaiting_confirmation',
        ]);
        $payment->order->update(['payment_status' => 'awaiting_confirmation']);

        return response()->json([
            'message' => 'Bukti pembayaran berhasil dikirim.',
            'status' => 'awaiting_confirmation',
            'proof_submitted_at' => $payment->proof_submitted_at,
            'whatsapp_url' => $this->whatsappUrl($payment),
        ]);
    }

    public function proof(Request $request, Payment $payment)
    {
        $payment->load('order');
        $this->ensureOrderAccess($request, $payment->order);
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->download(
            $payment->proof_path,
            $payment->proof_original_name ?: 'bukti-pembayaran-'.$payment->id
        );
    }

    public function confirmManualPayment(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_unless($payment->payment_mode === 'manual', 422, 'Pembayaran ini bukan pembayaran manual.');
        abort_unless($payment->proof_path, 422, 'Pelanggan belum mengunggah bukti pembayaran.');

        $payment = $this->applyMidtransStatus($payment, 'settlement');

        return response()->json([
            'message' => 'Pembayaran manual berhasil dikonfirmasi lunas.',
            'data' => $payment,
        ]);
    }

    private function whatsappUrl(Payment $payment): ?string
    {
        $number = PaymentSetting::current()->whatsapp_number;

        if (! $number) {
            return null;
        }

        $message = "Halo Lucky Pet, saya sudah membayar pesanan #{$payment->order_id} sebesar Rp "
            .number_format((int) $payment->gross_amount, 0, ',', '.')
            .'. Bukti pembayaran sudah saya unggah di website. Mohon dikonfirmasi.';

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }

    private function applyMidtransStatus(
        Payment $payment,
        string $transactionStatus,
        ?string $fraudStatus = null,
    ): Payment {
        $isPaid = in_array($transactionStatus, ['settlement', 'capture'], true)
            && ($transactionStatus !== 'capture' || $fraudStatus !== 'challenge');

        $status = match (true) {
            $isPaid => 'paid',
            $transactionStatus === 'expire' => 'expired',
            in_array($transactionStatus, ['cancel', 'deny', 'failure'], true) => 'failed',
            default => 'pending',
        };

        DB::transaction(function () use ($payment, $status) {
            $lockedPayment = Payment::lockForUpdate()->findOrFail($payment->id);
            $order = Order::with('items')->lockForUpdate()->findOrFail($lockedPayment->order_id);

            if ($order->payment_status === 'paid' && $status !== 'paid') {
                return;
            }

            $lockedPayment->update([
                'status' => $status,
                'paid_at' => $status === 'paid'
                    ? ($lockedPayment->paid_at ?? now())
                    : $lockedPayment->paid_at,
            ]);

            $order->update(['payment_status' => $status]);

            if ($status !== 'paid' || $order->stock_deducted_at) {
                return;
            }

            foreach ($order->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);

                if (! $product) {
                    continue;
                }

                $product->update([
                    'stock' => max(0, (int) $product->stock - (int) $item->quantity),
                    'sold_count' => (int) $product->sold_count + (int) $item->quantity,
                ]);
            }

            $order->update(['stock_deducted_at' => now()]);
        });

        return $payment->fresh();
    }

    private function ensureOrderAccess(Request $request, Order $order): void
    {
        $user = $request->user();

        abort_unless(
            $user && (
                $user->isAdmin()
                || $order->user_id === $user->id
                || (! $order->user_id && $order->customer_phone === $user->phone)
            ),
            403,
            'Anda tidak memiliki akses ke pembayaran ini.'
        );
    }
}
