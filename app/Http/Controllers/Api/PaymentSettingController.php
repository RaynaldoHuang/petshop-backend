<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\PaymentSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->data(PaymentSetting::current()));
    }

    public function update(Request $request): JsonResponse
    {
        $setting = PaymentSetting::current();
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['manual', 'realtime'])],
            'whatsapp_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'manual_qris' => [
                'nullable',
                'file',
                'max:5120',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
            ],
        ]);

        if ($validated['mode'] === 'manual' && ! $request->hasFile('manual_qris') && ! $setting->manual_qris_path) {
            return response()->json([
                'message' => 'Unggah QRIS terlebih dahulu sebelum mengaktifkan pembayaran manual.',
                'errors' => ['manual_qris' => ['File QRIS wajib diunggah untuk mode manual.']],
            ], 422);
        }

        if ($validated['mode'] === 'manual' && blank($validated['whatsapp_number'] ?? $setting->whatsapp_number)) {
            return response()->json([
                'message' => 'Nomor WhatsApp wajib diisi untuk pembayaran manual.',
                'errors' => ['whatsapp_number' => ['Nomor WhatsApp wajib diisi.']],
            ], 422);
        }

        if ($validated['mode'] === 'manual' && ! PaymentMethod::where('code', 'qris')->where('is_active', true)->exists()) {
            return response()->json([
                'message' => 'Aktifkan metode QRIS sebelum menggunakan pembayaran manual.',
            ], 422);
        }

        $data = [
            'mode' => $validated['mode'],
            'whatsapp_number' => $this->normalizeWhatsapp($validated['whatsapp_number'] ?? null),
        ];

        if ($file = $request->file('manual_qris')) {
            $data['manual_qris_path'] = $file->store('payment/qris', 'public');
            $data['manual_qris_mime'] = $file->getMimeType();
        }

        $setting->update($data);

        return response()->json([
            'message' => 'Pengaturan pembayaran berhasil diperbarui.',
            'data' => $this->data($setting->fresh()),
        ]);
    }

    private function data(PaymentSetting $setting): array
    {
        return [
            'mode' => $setting->mode,
            'whatsapp_number' => $setting->whatsapp_number,
            'manual_qris_url' => $setting->manual_qris_path
                ? Storage::disk('public')->url($setting->manual_qris_path)
                : null,
            'manual_qris_mime' => $setting->manual_qris_mime,
        ];
    }

    private function normalizeWhatsapp(?string $number): ?string
    {
        if (blank($number)) {
            return null;
        }

        $number = preg_replace('/\D+/', '', $number);

        if (str_starts_with($number, '0')) {
            return '62'.substr($number, 1);
        }

        return $number;
    }
}
