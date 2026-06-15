<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index()
    {
        return response()->json(
            User::query()
                ->whereIn('role', User::ADMIN_ROLES)
                ->latest()
                ->get(['id', 'name', 'phone', 'email', 'role', 'is_active', 'created_at'])
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(User::ADMIN_ROLES)],
            'is_active' => ['required', 'boolean'],
        ]);

        $user = User::create([
            ...$validated,
            'phone' => null,
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Akun admin berhasil dibuat.',
            'data' => $user->only([
                'id', 'name', 'phone', 'email', 'role', 'is_active', 'created_at',
            ]),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(User::ADMIN_ROLES)],
            'is_active' => ['required', 'boolean'],
        ]);

        abort_unless($user->isAdmin(), 404);

        if ($request->user()->is($user) && (
            $validated['role'] !== User::ROLE_SUPER_ADMIN ||
            ! $validated['is_active']
        )) {
            return response()->json([
                'message' => 'Akun super admin yang sedang digunakan tidak dapat dinonaktifkan atau diturunkan rolenya.',
            ], 422);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Akses pengguna berhasil diperbarui.',
            'data' => $user->only([
                'id', 'name', 'phone', 'email', 'role', 'is_active', 'created_at',
            ]),
        ]);
    }

    public function updatePassword(Request $request, User $user)
    {
        abort_unless($user->isAdmin(), 404);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        if (! $request->user()->is($user)) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => "Password {$user->name} berhasil diperbarui.",
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        abort_unless($user->isAdmin(), 404);

        if ($request->user()->is($user)) {
            return response()->json([
                'message' => 'Akun yang sedang digunakan tidak dapat dihapus.',
            ], 422);
        }

        if (
            $user->isSuperAdmin()
            && User::where('role', User::ROLE_SUPER_ADMIN)->count() <= 1
        ) {
            return response()->json([
                'message' => 'Super admin terakhir tidak dapat dihapus.',
            ], 422);
        }

        $name = $user->name;
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => "Akun {$name} berhasil dihapus.",
        ]);
    }
}
