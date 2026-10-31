<?php

namespace Tests\Feature;

use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::where('email', 'admin@arklehomes.com.au')->first();
        if (!$user) {
            $this->artisan('db:seed');
            $user = User::where('email', 'admin@arklehomes.com.au')->first();
        }

        $response = $this->post('/login', [
            'email' => 'admin@arklehomes.com.au',
            'password' => 'Admin@12345',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard'));

        // Check login event
        $this->assertDatabaseHas('login_events', [
            'email' => 'admin@arklehomes.com.au',
            'status' => 'success',
        ]);
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@arklehomes.com.au',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');

        // Check failed login event
        $this->assertDatabaseHas('login_events', [
            'email' => 'admin@arklehomes.com.au',
            'status' => 'failed',
            'failure_reason' => 'Invalid credentials',
        ]);
    }

    public function test_inactive_users_cannot_log_in(): void
    {
        $employee = User::where('email', 'ishan@arklehomes.com.au')->first();
        $employee->update(['status' => 'inactive']);

        $response = $this->post('/login', [
            'email' => 'ishan@arklehomes.com.au',
            'password' => 'Admin@12345',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::where('email', 'admin@arklehomes.com.au')->first();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }
}
