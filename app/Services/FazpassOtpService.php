<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class FazpassOtpService
{
    public function send(string $phone, string $purpose): array
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
            ->withToken($this->apiKey())
            ->post($this->url($this->sendPath()), [
                'phone' => $phone,
                'purpose' => $purpose,
                'channel' => config('services.fazpass.channel', 'whatsapp'),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('message') ?: 'Fazpass gagal mengirim OTP.');
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

    public function verify(string $phone, string $otpCode, ?string $reference = null): bool
    {
        if (! $this->enabled()) {
            return $otpCode === $this->testCode();
        }

        $response = Http::timeout($this->timeout())
            ->acceptJson()
            ->withToken($this->apiKey())
            ->post($this->url($this->verifyPath()), [
                'phone' => $phone,
                'otp' => $otpCode,
                'code' => $otpCode,
                'reference_id' => $reference,
                'transaction_id' => $reference,
                'otp_id' => $reference,
            ]);

        if (! $response->successful()) {
            return false;
        }

        $json = $response->json();
        $status = strtolower((string) (data_get($json, 'status') ?: data_get($json, 'data.status')));

        return data_get($json, 'success') === true
            || data_get($json, 'verified') === true
            || data_get($json, 'data.verified') === true
            || in_array($status, ['success', 'verified', 'ok'], true);
    }

    public function enabled(): bool
    {
        return (bool) config('services.fazpass.enabled');
    }

    public function testCode(): string
    {
        return (string) config('services.fazpass.test_code', '8888');
    }

    private function apiKey(): string
    {
        $key = (string) config('services.fazpass.api_key');

        if ($key === '') {
            throw new RuntimeException('FAZPASS_API_KEY belum diisi.');
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
        return (string) config('services.fazpass.send_path', '/otp/send');
    }

    private function verifyPath(): string
    {
        return (string) config('services.fazpass.verify_path', '/otp/verify');
    }

    private function timeout(): int
    {
        return (int) config('services.fazpass.timeout', 15);
    }
}
