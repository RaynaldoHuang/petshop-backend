<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FazpassOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly FazpassOtpService $otpService)
    {
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $otpToken = $this->createOtpChallenge('register', [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'requires_otp' => true,
            'otp_token' => $otpToken,
            'message' => 'Kode OTP sudah dikirim.',
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'otp_token' => ['required', 'string'],
            'otp_code' => ['required', 'string'],
        ]);

        $challenge = $this->consumeOtpChallenge($validated['otp_token'], $validated['otp_code']);

        if ($challenge['type'] === 'register') {
            if (User::where('phone', $challenge['payload']['phone'])->exists()) {
                throw ValidationException::withMessages([
                    'phone' => ['Nomor telepon sudah terdaftar.'],
                ]);
            }

            $user = User::create([
                'name' => $challenge['payload']['name'],
                'phone' => $challenge['payload']['phone'],
                'role' => null,
                'phone_verified_at' => now(),
                'password' => $challenge['payload']['password'],
            ]);

            return response()->json($this->tokenResponse($user));
        }

        if ($challenge['type'] === 'login') {
            $user = User::whereKey($challenge['payload']['user_id'])
                ->whereNull('role')
                ->firstOrFail();

            if (! $user->phone_verified_at) {
                $user->forceFill(['phone_verified_at' => now()])->save();
            }

            return response()->json($this->tokenResponse($user));
        }

        throw ValidationException::withMessages([
            'otp_token' => ['Token OTP tidak valid.'],
        ]);
    }

    public function requestPasswordResetOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $user = User::where('phone', $validated['phone'])
            ->whereNull('role')
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => ['Nomor telepon tidak terdaftar.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Akun Anda sedang dinonaktifkan.',
            ], 403);
        }

        $otpToken = $this->createOtpChallenge('forgot_password', [
            'user_id' => $user->id,
            'phone' => $user->phone,
            'name' => $user->name,
        ]);

        return response()->json([
            'requires_otp' => true,
            'otp_token' => $otpToken,
            'message' => 'Kode OTP sudah dikirim.',
        ]);
    }

    public function resetPasswordWithOtp(Request $request)
    {
        $validated = $request->validate([
            'otp_token' => ['required', 'string'],
            'otp_code' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $challenge = $this->consumeOtpChallenge($validated['otp_token'], $validated['otp_code'], 'forgot_password');

        $user = User::whereKey($challenge['payload']['user_id'])
            ->whereNull('role')
            ->firstOrFail();

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'phone_verified_at' => $user->phone_verified_at ?: now(),
        ])->save();

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password berhasil diubah. Silakan login kembali.',
        ]);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'otp_code' => ['required', 'string'],
            'otp_token' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'Endpoint ini hanya untuk akun customer.',
            ], 403);
        }

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password lama tidak sesuai.'],
            ]);
        }

        if (! empty($validated['otp_token'])) {
            $challenge = $this->consumeOtpChallenge($validated['otp_token'], $validated['otp_code'], 'change_password');

            if ((int) $challenge['payload']['user_id'] !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'otp_token' => ['Token OTP tidak sesuai.'],
                ]);
            }
        } elseif ($this->otpService->enabled()) {
            throw ValidationException::withMessages([
                'otp_token' => ['Silakan minta kode OTP terlebih dahulu.'],
            ]);
        } elseif (! $this->otpService->verifyTestingCode($validated['otp_code'])) {
            throw ValidationException::withMessages([
                'otp_code' => ['Kode OTP tidak valid.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'phone_verified_at' => $user->phone_verified_at ?: now(),
        ])->save();

        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
        ]);
    }

    public function requestPasswordChangeOtp(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'Endpoint ini hanya untuk akun customer.',
            ], 403);
        }

        $otpToken = $this->createOtpChallenge('change_password', [
            'user_id' => $user->id,
            'phone' => $user->phone,
            'name' => $user->name,
        ]);

        return response()->json([
            'requires_otp' => true,
            'otp_token' => $otpToken,
            'message' => 'Kode OTP sudah dikirim.',
        ]);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required'],
            'password' => ['required'],
        ]);

        $user = User::where('phone', $validated['phone'])
            ->whereNull('role')
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Nomor telepon atau password salah',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Akun Anda sedang dinonaktifkan.',
            ], 403);
        }

        $otpToken = $this->createOtpChallenge('login', [
            'user_id' => $user->id,
            'phone' => $user->phone,
            'name' => $user->name,
        ]);

        return response()->json([
            'requires_otp' => true,
            'otp_token' => $otpToken,
            'message' => 'Kode OTP sudah dikirim.',
        ]);
    }

    public function adminLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! $user->isAdmin() || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password admin salah.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Akun admin sedang dinonaktifkan.',
            ], 403);
        }

        $token = $user->createToken('admin_auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil',
        ]);
    }

    private function createOtpChallenge(string $type, array $payload): string
    {
        $token = (string) Str::uuid();
        $otpCode = $this->makeOtpCode();
        $delivery = $this->otpService->send(
            $payload['phone'],
            $otpCode,
            $type,
            $this->otpParams($payload, $type)
        );

        Cache::put($this->otpCacheKey($token), [
            'type' => $type,
            'payload' => $payload,
            'provider' => $delivery['provider'],
            'reference' => $delivery['reference'],
            'otp_hash' => Hash::make($otpCode),
        ], now()->addMinutes(10));

        return $token;
    }

    private function consumeOtpChallenge(string $token, string $otpCode, ?string $expectedType = null): array
    {
        $key = $this->otpCacheKey($token);
        $challenge = Cache::get($key);

        if (! $challenge) {
            throw ValidationException::withMessages([
                'otp_token' => ['Token OTP sudah kedaluwarsa atau tidak valid.'],
            ]);
        }

        if ($expectedType && $challenge['type'] !== $expectedType) {
            throw ValidationException::withMessages([
                'otp_token' => ['Token OTP tidak sesuai.'],
            ]);
        }

        if (! Hash::check($otpCode, $challenge['otp_hash'] ?? '')) {
            throw ValidationException::withMessages([
                'otp_code' => ['Kode OTP tidak valid.'],
            ]);
        }

        Cache::forget($key);

        return $challenge;
    }

    private function otpCacheKey(string $token): string
    {
        return "auth_otp:{$token}";
    }

    private function makeOtpCode(): string
    {
        if (! $this->otpService->enabled()) {
            return $this->otpService->testCode();
        }

        $length = $this->otpService->codeLength();
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;

        return (string) random_int($min, $max);
    }

    private function otpParams(array $payload, string $type): array
    {
        return [
            [
                'tag' => (string) config('services.fazpass.name_tag', 'name'),
                'value' => (string) ($payload['name'] ?? 'Customer'),
            ],
            [
                'tag' => 'purpose',
                'value' => $this->otpPurposeLabel($type),
            ],
        ];
    }

    private function otpPurposeLabel(string $type): string
    {
        return match ($type) {
            'register' => 'daftar akun',
            'login' => 'login',
            'forgot_password' => 'reset password',
            'change_password' => 'ganti password',
            default => 'verifikasi akun',
        };
    }

    private function tokenResponse(User $user): array
    {
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
