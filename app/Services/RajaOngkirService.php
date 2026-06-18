<?php

namespace App\Services;

use App\Models\RajaOngkirSetting;
use App\Models\ShippingCourier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RajaOngkirService
{
    public function settings(): array
    {
        $setting = RajaOngkirSetting::query()->first();

        return [
            'origin_destination_id' => $setting?->origin_destination_id
                ?: config('services.rajaongkir.origin_city_id'),
            'origin_province' => $setting?->origin_province,
            'origin_city' => $setting?->origin_city,
            'origin_district' => $setting?->origin_district,
            'origin_subdistrict' => $setting?->origin_subdistrict,
            'origin_zip_code' => $setting?->origin_zip_code,
            'default_item_weight' => $setting?->default_item_weight
                ?: (int) config('services.rajaongkir.default_item_weight'),
            'is_active' => $setting?->is_active ?? true,
            'active_couriers' => ($setting?->is_active ?? true)
                ? $this->activeCouriers()
                : [],
        ];
    }

    public function activeCouriers(): array
    {
        $totalCouriers = ShippingCourier::query()->count();
        $couriers = ShippingCourier::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name']);

        if ($totalCouriers === 0) {
            return [
                ['code' => 'jne', 'name' => 'JNE'],
                ['code' => 'pos', 'name' => 'POS Indonesia'],
                ['code' => 'tiki', 'name' => 'TIKI'],
            ];
        }

        return $couriers->toArray();
    }

    public function provinces(): array
    {
        if ($this->usesKomerceApi()) {
            $provinces = Cache::remember('rajaongkir.provinces', now()->addDay(), function () {
                return $this->request('get', '/destination/province')['data'] ?? [];
            });

            return collect($provinces)
                ->map(fn (array $province) => [
                    'province_id' => (string) $province['id'],
                    'province' => $province['name'],
                ])
                ->values()
                ->all();
        }

        return Cache::remember('rajaongkir.classic.provinces', now()->addDay(), function () {
            return $this->request('get', '/province')['rajaongkir']['results'] ?? [];
        });
    }

    public function cities(?string $provinceId = null): array
    {
        if ($this->usesKomerceApi()) {
            if (! $provinceId) {
                return [];
            }

            $cities = Cache::remember(
                'rajaongkir.cities.'.$provinceId,
                now()->addDay(),
                fn () => $this->request('get', '/destination/city/'.$provinceId)['data'] ?? []
            );

            return collect($cities)
                ->map(fn (array $city) => [
                    'city_id' => (string) $city['id'],
                    'province_id' => (string) $provinceId,
                    'province' => '',
                    'type' => 'Kota/Kab.',
                    'city_name' => $city['name'],
                    'postal_code' => '',
                ])
                ->values()
                ->all();
        }

        $query = $provinceId ? ['province' => $provinceId] : [];

        return Cache::remember(
            'rajaongkir.classic.cities.'.($provinceId ?: 'all'),
            now()->addDay(),
            fn () => $this->request('get', '/city', $query)['rajaongkir']['results'] ?? []
        );
    }

    public function districts(string $cityId): array
    {
        if ($this->usesKomerceApi()) {
            $districts = Cache::remember(
                'rajaongkir.districts.'.$cityId,
                now()->addDay(),
                fn () => $this->request('get', '/destination/district/'.$cityId)['data'] ?? []
            );

            return collect($districts)
                ->map(fn (array $district) => [
                    'district_id' => (string) $district['id'],
                    'city_id' => (string) $cityId,
                    'district_name' => $district['name'],
                ])
                ->values()
                ->all();
        }

        return [];
    }

    public function subDistricts(string $districtId): array
    {
        if ($this->usesKomerceApi()) {
            $subDistricts = Cache::remember(
                'rajaongkir.sub_districts.'.$districtId,
                now()->addDay(),
                fn () => $this->request('get', '/destination/sub-district/'.$districtId)['data'] ?? []
            );

            return collect($subDistricts)
                ->map(fn (array $subDistrict) => [
                    'subdistrict_id' => (string) $subDistrict['id'],
                    'destination_id' => (string) $subDistrict['id'],
                    'district_id' => (string) $districtId,
                    'subdistrict_name' => $subDistrict['name'],
                    'zip_code' => $subDistrict['zip_code'] ?? '',
                ])
                ->values()
                ->all();
        }

        return [];
    }

    public function domesticDestinations(string $search): array
    {
        if (! $this->usesKomerceApi()) {
            return [];
        }

        $destinations = $this->request('get', '/destination/domestic-destination', [
            'search' => $search,
        ])['data'] ?? [];

        return collect($destinations)
            ->map(fn (array $destination) => [
                'destination_id' => (string) $destination['id'],
                'label' => $destination['label'],
                'province' => $destination['province_name'] ?? '',
                'city' => $destination['city_name'] ?? '',
                'district' => $destination['district_name'] ?? '',
                'subdistrict' => $destination['subdistrict_name'] ?? '',
                'zip_code' => $destination['zip_code'] ?? '',
            ])
            ->values()
            ->all();
    }

    public function cost(
        string $destination,
        int $weight,
        string $courier
    ): array {
        $origin = $this->settings()['origin_destination_id'];

        if (! $this->settings()['is_active']) {
            throw new RuntimeException('RajaOngkir sedang nonaktif.');
        }

        if (! $origin) {
            throw new RuntimeException('Asal pengiriman RajaOngkir belum diisi.');
        }

        if ($this->usesKomerceApi()) {
            $services = Cache::remember(
                "rajaongkir.cost.{$origin}.{$destination}.{$weight}.{$courier}",
                now()->addMinutes(30),
                fn () => $this->request('post', '/calculate/domestic-cost', [
                    'origin' => $origin,
                    'destination' => $destination,
                    'weight' => max(1, $weight),
                    'courier' => $courier,
                ])['data'] ?? []
            );

            return [[
                'code' => $courier,
                'name' => $services[0]['name'] ?? strtoupper($courier),
                'costs' => collect($services)
                    ->map(fn (array $service) => [
                        'service' => $service['service'],
                        'description' => $service['description'] ?? $service['service'],
                        'cost' => [[
                            'value' => (int) $service['cost'],
                            'etd' => $service['etd'] ?? '',
                            'note' => '',
                        ]],
                    ])
                    ->values()
                    ->all(),
            ]];
        }

        $response = Cache::remember(
            "rajaongkir.classic.cost.{$origin}.{$destination}.{$weight}.{$courier}",
            now()->addMinutes(30),
            fn () => $this->request('post', '/cost', [
                'origin' => $origin,
                'destination' => $destination,
                'weight' => max(1, $weight),
                'courier' => $courier,
            ])
        );

        return $response['rajaongkir']['results'] ?? [];
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $key = config('services.rajaongkir.key');

        if (! $key) {
            throw new RuntimeException('RAJAONGKIR_API_KEY belum diisi.');
        }

        $baseUrl = rtrim((string) config('services.rajaongkir.base_url'), '/');
        $request = Http::withHeaders([
            'key' => $key,
            'x-api-key' => $key,
        ])
            ->acceptJson()
            ->asForm()
            ->timeout(20);

        $response = $method === 'post'
            ? $request->post($baseUrl.$path, $payload)
            : $request->get($baseUrl.$path, $payload);

        if (! $response->successful()) {
            $message = $response->json('meta.message')
                ?: $response->json('rajaongkir.status.description')
                ?: $response->reason()
                ?: 'RajaOngkir belum dapat dihubungi.';

            throw new RuntimeException("RajaOngkir: {$message}");
        }

        return $response->json();
    }

    private function usesKomerceApi(): bool
    {
        return str_contains((string) config('services.rajaongkir.base_url'), 'komerce');
    }
}
