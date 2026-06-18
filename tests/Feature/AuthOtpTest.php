<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_login_requires_otp_before_token_is_issued(): void
    {
        User::factory()->create([
            'phone' => '081200000002',
            'password' => 'password123',
            'role' => null,
        ]);

        $response = $this->postJson('/api/login', [
            'phone' => '081200000002',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('requires_otp', true)
            ->assertJsonMissingPath('token');

        $this->postJson('/api/auth/verify-otp', [
            'otp_token' => $response->json('otp_token'),
            'otp_code' => '8888',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_wrong_otp_is_rejected(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Customer Otp',
            'phone' => '081200000003',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->postJson('/api/auth/verify-otp', [
            'otp_token' => $response->json('otp_token'),
            'otp_code' => '1234',
        ])->assertUnprocessable();
    }

    public function test_forgot_password_resets_password_with_otp(): void
    {
        $user = User::factory()->create([
            'phone' => '081200000004',
            'password' => 'old-password',
            'role' => null,
        ]);

        $response = $this->postJson('/api/forgot-password/request-otp', [
            'phone' => '081200000004',
        ])->assertOk();

        $this->postJson('/api/forgot-password/reset', [
            'otp_token' => $response->json('otp_token'),
            'otp_code' => '8888',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_logged_in_customer_can_change_password_with_current_password_and_otp(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
            'role' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/password/change', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            'otp_code' => '8888',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }
}
