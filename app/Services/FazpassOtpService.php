<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class FazpassOtpService
{
    public function send(string $phone, string $otpCode, string $purpose, array $params = []): array
    {
        if (! $this->enabled()) {
            return [
                'provider' => 'testing',
                'reference' => null,
                'message' => 'Kode OTP testing: '.$this->testCode(),
            ];
        }

        $response = Http::timeout($this->timeout())
            ->acceptJson()
            ->withToken($this->merchantKey())
            ->post($this->url($this->sendPath()), [
                'phone' => $this->normalizePhone($phone),
                'otp' => $otpCode,
                'gateway_key' => $this->gatewayKey(),
                'params' => $params,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json()));
        }

        $json = $response->json();

        return [
            'provider' => 'fazpass',
            'reference' => data_get($json, 'data.transaction_id')
                ?: data_get($json, 'data.otp_id')
                ?: data_get($json, 'data.reference_id')
                ?: data_get($json, 'transaction_id')
                ?: data_get($json, 'otp_id')
                ?: data_get($json, 'reference_id'),
            'message' => data_get($json, 'message', 'Kode OTP sudah dikirim.'),
            'response' => $json,
        ];
    }

    public function verifyTestingCode(string $otpCode): bool
    {
        return $otpCode === $this->testCode();
    }

    public function enabled(): bool
    {
        return (bool) config('services.fazpass.enabled');
    }

    public function testCode(): string
    {
        return (string) config('services.fazpass.test_code', '8888');
    }

    public function codeLength(): int
    {
        return max(4, (int) config('services.fazpass.code_length', 6));
    }

    private function merchantKey(): string
    {
        $key = (string) config('services.fazpass.merchant_key');

        if ($key === '') {
            throw new RuntimeException('FAZPASS_MERCHANT_KEY belum diisi.');
        }

        return $key;
    }

    private function gatewayKey(): string
    {
        $key = (string) config('services.fazpass.gateway_key');

        if ($key === '') {
            throw new RuntimeException('FAZPASS_GATEWAY_KEY belum diisi.');
        }

        return $key;
    }

    private function url(string $path): string
    {
        $baseUrl = rtrim((string) config('services.fazpass.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('FAZPASS_BASE_URL belum diisi.');
        }

        return $baseUrl.'/'.ltrim($path, '/');
    }

    private function sendPath(): string
    {
        return (string) config('services.fazpass.send_path', '/v1/otp/send');
    }

    private function timeout(): int
    {
        return (int) config('services.fazpass.timeout', 15);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?: $phone;

        if (str_starts_with($phone, '0')) {
            return '62'.substr($phone, 1);
        }

        return $phone;
    }

    private function errorMessage(?array $json): string
    {
        $errors = collect(data_get($json, 'errors', []))
            ->map(fn ($error) => is_array($error)
                ? (data_get($error, 'message') ?: data_get($error, 'field') ?: json_encode($error))
                : (string) $error)
            ->filter()
            ->implode(', ');

        if ($errors !== '') {
            return $errors;
        }

        return data_get($json, 'message')
            ?: data_get($json, 'data.message')
            ?: data_get($json, 'meta.message')
            ?: 'Fazpass gagal mengirim OTP.';
    }
}
