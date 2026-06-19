<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\RajaOngkirService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class RajaOngkirController extends Controller
{
    public function __construct(
        private readonly RajaOngkirService $rajaOngkir
    ) {}

    public function provinces(): JsonResponse
    {
        try {
            return response()->json($this->rajaOngkir->provinces());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }
    }

    public function cities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'province_id' => ['nullable', 'string'],
        ]);

        try {
            return response()->json(
                $this->rajaOngkir->cities($validated['province_id'] ?? null)
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }
    }

    public function districts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => ['required', 'string'],
        ]);

        try {
            return response()->json(
                $this->rajaOngkir->districts($validated['city_id'])
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }
    }

    public function subDistricts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'district_id' => ['required', 'string'],
        ]);

        try {
            return response()->json(
                $this->rajaOngkir->subDistricts($validated['district_id'])
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }
    }

    public function config(): JsonResponse
    {
        return response()->json($this->rajaOngkir->settings());
    }

    public function costs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'destination' => ['required', 'string'],
            'courier' => [
                'required',
                'string',
                Rule::in(collect($this->rajaOngkir->activeCouriers())->pluck('code')->all()),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $weight = $this->calculateWeight($validated['items']);

        try {
            $results = $this->rajaOngkir->cost(
                $validated['destination'],
                $weight,
                $validated['courier']
            );

            return response()->json([
                'weight' => $weight,
                'results' => $results,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }
    }

    private function calculateWeight(array $items): int
    {
        $defaultWeight = (int) $this->rajaOngkir->settings()['default_item_weight'];
        $defaultWeight = max(1, $defaultWeight);

        return collect($items)
            ->groupBy('id')
            ->map(function ($rows, $productId) use ($defaultWeight) {
                $product = Product::findOrFail($productId);
                $productWeight = max(1, (int) ($product->weight_grams ?: $defaultWeight));

                return $rows->sum('quantity') * $productWeight;
            })
            ->sum();
    }
}
