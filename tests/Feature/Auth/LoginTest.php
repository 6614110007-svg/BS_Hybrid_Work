<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('เข้าสู่ระบบ');
    }

    public function test_admin_can_login_and_is_redirected_to_admin_dashboard(): void
    {
        $admin = Admin::factory()->create([
            'admin_email' => 'admin@example.test',
            'admin_password' => 'secret-pass',
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'secret-pass',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_employee_can_login_and_is_redirected_to_employee_dashboard(): void
    {
        $employee = Employee::factory()->create([
            'employee_email' => 'staff@example.test',
            'employee_password' => 'secret-pass',
            'employee_role' => Employee::ROLE_EMPLOYEE,
            'department_id' => Department::factory(),
        ]);

        $response = $this->post('/login', [
            'email' => 'staff@example.test',
            'password' => 'secret-pass',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($employee, 'web');
    }

    public function test_employee_with_administrator_role_goes_to_admin_dashboard(): void
    {
        $employee = Employee::factory()->administrator()->create([
            'employee_email' => 'manager@example.test',
            'employee_password' => 'secret-pass',
        ]);

        $response = $this->post('/login', [
            'email' => 'manager@example.test',
            'password' => 'secret-pass',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($employee, 'web');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        Employee::factory()->create(['employee_email' => 'staff@example.test']);

        $response = $this->from('/login')->post('/login', [
            'email' => 'staff@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
        $this->assertGuest('admin');
    }

    public function test_inactive_employee_cannot_login(): void
    {
        Employee::factory()->inactive()->create([
            'employee_email' => 'staff@example.test',
            'employee_password' => 'secret-pass',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'staff@example.test',
            'password' => 'secret-pass',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_inactive_admin_cannot_login(): void
    {
        Admin::factory()->inactive()->create([
            'admin_email' => 'admin@example.test',
            'admin_password' => 'secret-pass',
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'admin@example.test',
                'password' => 'secret-pass',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_actor_can_logout_from_every_guard(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($admin, 'admin');
        $this->actingAs($employee, 'web');

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest('admin');
        $this->assertGuest('web');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }
}
