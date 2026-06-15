<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'role' => null,
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
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

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
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
}
