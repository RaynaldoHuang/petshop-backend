<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RajaOngkirSetting;
use App\Models\ShippingCourier;
use App\Services\RajaOngkirService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RajaOngkirAdminController extends Controller
{
    public function __construct(
        private readonly RajaOngkirService $rajaOngkir
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'setting' => $this->rajaOngkir->settings(),
            'couriers' => ShippingCourier::query()
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function updateSetting(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origin_destination_id' => ['required', 'string', 'max:50'],
            'origin_province' => ['required', 'string', 'max:255'],
            'origin_city' => ['required', 'string', 'max:255'],
            'origin_district' => ['required', 'string', 'max:255'],
            'origin_subdistrict' => ['required', 'string', 'max:255'],
            'origin_zip_code' => ['required', 'string', 'max:20'],
            'default_item_weight' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);

        $setting = RajaOngkirSetting::query()->firstOrNew();
        $setting->fill($validated)->save();

        return response()->json([
            'message' => 'Setting RajaOngkir berhasil disimpan.',
            'data' => $setting,
        ]);
    }

    public function storeCourier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:shipping_couriers,code'],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $courier = ShippingCourier::create([
            ...$validated,
            'code' => strtolower($validated['code']),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json($courier, 201);
    }

    public function updateCourier(Request $request, ShippingCourier $courier): JsonResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('shipping_couriers', 'code')->ignore($courier->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $courier->update([
            ...$validated,
            'code' => strtolower($validated['code']),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json($courier);
    }

    public function destroyCourier(ShippingCourier $courier): JsonResponse
    {
        $courier->delete();

        return response()->json([
            'message' => 'Kurir berhasil dihapus.',
        ]);
    }

    public function destinations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        return response()->json(
            $this->rajaOngkir->domesticDestinations($validated['search'])
        );
    }
}
