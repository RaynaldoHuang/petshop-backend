<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    private const SUPPORTED_METHODS = [
        'qris' => 'qris',
        'bca' => 'bank_transfer',
        'bni' => 'bank_transfer',
        'bri' => 'bank_transfer',
        'mandiri' => 'bank_transfer',
        'permata' => 'bank_transfer',
    ];

    public function index()
    {
        $query = PaymentMethod::where('is_active', true);

        if (PaymentSetting::current()->isManual()) {
            $query->where('code', 'qris');
        }

        return response()->json($query->orderBy('sort_order')->get());
    }

    public function adminIndex()
    {
        return response()->json(
            PaymentMethod::orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $method = PaymentMethod::create($this->validated($request));

        return response()->json([
            'message' => 'Metode pembayaran berhasil ditambahkan.',
            'data' => $method,
        ], 201);
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $paymentMethod->update($this->validated($request, $paymentMethod));

        return response()->json([
            'message' => 'Metode pembayaran berhasil diperbarui.',
            'data' => $paymentMethod->fresh(),
        ]);
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        $paymentMethod->delete();

        return response()->json([
            'message' => 'Metode pembayaran berhasil dihapus.',
        ]);
    }

    private function validated(Request $request, ?PaymentMethod $paymentMethod = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                Rule::in(array_keys(self::SUPPORTED_METHODS)),
                Rule::unique('payment_methods', 'code')->ignore($paymentMethod?->id),
            ],
            'fee' => ['required', 'integer', 'min:0'],
            'fee_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'icon' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $validated['type'] = self::SUPPORTED_METHODS[$validated['code']];

        return $validated;
    }
}
