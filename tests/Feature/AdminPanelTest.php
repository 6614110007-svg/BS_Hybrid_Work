<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
    }

    public function test_employee_cannot_access_admin_pages(): void
    {
        $employee = User::factory()->create();

        $response = $this->actingAs($employee)->get('/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create([
            'password' => 'password123',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_temp_password_user_is_forced_to_change_password(): void
    {
        $user = User::factory()->create([
            'password' => 'temporary-pass',
            'must_change_password' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'temporary-pass',
        ]);

        $response->assertRedirect(route('password.change'));
    }

    public function test_admin_login_redirects_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create([
            'password' => 'Secret12345',
        ]);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'Secret12345',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_can_create_department(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/departments', [
            'code' => 'IT',
            'name' => 'แผนกเทคโนโลยี',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.departments.index'));
        $this->assertDatabaseHas('departments', [
            'code' => 'IT',
            'name' => 'แผนกเทคโนโลยี',
        ]);
    }

    public function test_admin_can_create_employee_with_temp_password(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::create(['code' => 'IT', 'name' => 'ไอที']);

        $response = $this->actingAs($admin)->from(route('admin.employees.index'))
            ->post('/admin/employees', [
                'name' => 'สมชาย ใจดี',
                'email' => 'somchai@example.com',
                'employee_code' => 'EMP-001',
                'department_id' => $department->id,
                'role' => 'employee',
            ]);

        $response->assertRedirect(route('admin.employees.index'));

        $user = User::where('email', 'somchai@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertSame(User::STATUS_ACTIVE, $user->employee_status);
        $response->assertSessionHas('temp_password');
    }
}