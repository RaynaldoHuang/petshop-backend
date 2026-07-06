<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_access_admin_endpoints(): void
    {
        $customer = User::factory()->create([
            'role' => null,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }

    public function test_admin_can_receive_orders_and_edit_images_without_payment_access(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        Order::create([
            'customer_name' => 'Customer Baru',
            'customer_phone' => '081200000002',
            'shipping_address' => 'Jl. Mawar No. 1',
            'total_price' => 150000,
            'payment_status' => 'paid',
            'order_status' => 'new',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertForbidden();

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonMissingPath('0.payment_status');

        $this->getJson('/api/admin/hero-sections')
            ->assertOk();

        $this->postJson('/api/admin/payment-methods', [
            'name' => 'BNI Virtual Account',
            'code' => 'bni',
            'fee' => 4000,
            'fee_percentage' => 0.7,
            'is_active' => true,
            'sort_order' => 2,
        ])
            ->assertForbidden();

        $this->getJson('/api/admin/customers')
            ->assertForbidden();
    }

    public function test_super_admin_can_access_dashboard_and_manage_payment_methods(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        Sanctum::actingAs($superAdmin);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'revenue',
                    'orders',
                    'pending_orders',
                    'products',
                    'customers',
                    'active_payment_methods',
                ],
                'recent_orders',
                'low_stock_products',
            ]);

        $this->postJson('/api/admin/payment-methods', [
            'name' => 'BNI Virtual Account',
            'code' => 'bni',
            'fee' => 4000,
            'fee_percentage' => 0.7,
            'is_active' => true,
            'sort_order' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'bank_transfer')
            ->assertJsonPath('data.fee_percentage', 0.7);
    }

    public function test_only_super_admin_can_manage_admin_accounts(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertForbidden();

        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        Sanctum::actingAs($superAdmin);

        $this->postJson('/api/admin/users', [
            'name' => 'Admin Operasional',
            'email' => 'operator@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', User::ROLE_ADMIN);
    }

    public function test_admin_login_uses_email_and_password(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'role' => User::ROLE_ADMIN,
            'password' => 'password123',
        ]);

        $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', User::ROLE_ADMIN);
    }

    public function test_super_admin_can_change_admin_password_and_delete_admin_account(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'password' => 'old-password',
        ]);

        Sanctum::actingAs($superAdmin);

        $this->putJson("/api/admin/users/{$admin->id}/password", [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password123', $admin->fresh()->password));

        $this->deleteJson("/api/admin/users/{$admin->id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $admin->id]);
    }

    public function test_super_admin_cannot_delete_own_account(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        Sanctum::actingAs($superAdmin);

        $this->deleteJson("/api/admin/users/{$superAdmin->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }

    public function test_only_super_admin_can_delete_customer_account(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $customer = User::factory()->create([
            'role' => null,
            'phone' => '081299999999',
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/customers/{$customer->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $customer->id]);

        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        Sanctum::actingAs($superAdmin);

        $this->deleteJson("/api/admin/customers/{$customer->id}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);

        $this->deleteJson("/api/admin/customers/{$admin->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_public_registration_creates_a_customer_without_admin_role(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Customer Baru',
            'phone' => '081200000001',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => User::ROLE_ADMIN,
        ])->assertOk()
            ->assertJsonPath('requires_otp', true);

        $this->assertDatabaseMissing('users', [
            'phone' => '081200000001',
        ]);

        $this->postJson('/api/auth/verify-otp', [
            'otp_token' => $response->json('otp_token'),
            'otp_code' => '8888',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('users', [
            'phone' => '081200000001',
            'role' => null,
        ]);
    }
}
