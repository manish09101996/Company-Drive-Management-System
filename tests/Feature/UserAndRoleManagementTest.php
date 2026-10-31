<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserAndRoleManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_super_admin_cannot_be_deleted(): void
    {
        $superAdmin = User::where('email', 'admin@arklehomes.com.au')->first();
        $admin = User::where('email', 'skmann@arklehomes.com.au')->first();

        // Direct policy rejects non-super-admin with 403 Forbidden
        $response = $this->actingAs($admin)->delete("/users/{$superAdmin->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id, 'deleted_at' => null]);
    }

    public function test_super_admin_cannot_be_deactivated(): void
    {
        $superAdmin = User::where('email', 'admin@arklehomes.com.au')->first();
        $admin = User::where('email', 'skmann@arklehomes.com.au')->first();

        // Direct policy rejects unauthorized modification of Super Admin with 403 Forbidden
        $response = $this->actingAs($admin)->post("/users/{$superAdmin->id}/toggle-status");

        $response->assertStatus(403);
        $this->assertEquals('active', $superAdmin->fresh()->status);
    }

    public function test_non_super_admin_cannot_grant_super_admin_role(): void
    {
        $admin = User::where('email', 'skmann@arklehomes.com.au')->first();
        $superAdminRole = Role::where('slug', 'super-admin')->first();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Malicious Escalation',
            'email' => 'escalate@arklehomes.com.au',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'status' => 'active',
            'roles' => [$superAdminRole->id],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'escalate@arklehomes.com.au']);
    }

    public function test_admin_can_create_new_employee(): void
    {
        $admin = User::where('email', 'skmann@arklehomes.com.au')->first();
        $employeeRole = Role::where('slug', 'employee')->first();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Michael Scott',
            'email' => 'mscott@arklehomes.com.au',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'status' => 'active',
            'roles' => [$employeeRole->id],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'mscott@arklehomes.com.au']);
    }
}
