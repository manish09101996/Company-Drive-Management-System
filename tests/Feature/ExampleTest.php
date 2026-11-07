<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Test that root redirects to login for guests.
     */
    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    /**
     * Test that login page is accessible.
     */
    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Arkle Homes Drive');
    }

    /**
     * Test that admin dashboard displays 50 GB storage quota and metrics.
     */
    public function test_dashboard_displays_50gb_storage_quota_and_metrics(): void
    {
        $admin = \App\Models\User::where('email', 'admin@arklehomes.com.au')->firstOrFail();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('50 GB');
        $response->assertSee('3.18 GB');
        $response->assertSee('Server Free:');
    }

    public function test_db_status_endpoint_renders_diagnostic_screen(): void
    {
        $response = $this->get('/db-status?key=ArkleDrive2026');

        $response->assertStatus(200);
        $response->assertSee('Database Diagnostic');
    }
}
