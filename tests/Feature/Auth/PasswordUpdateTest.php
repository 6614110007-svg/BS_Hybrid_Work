<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_change_own_password(): void
    {
        $employee = Employee::factory()->create(['employee_password' => 'old-password']);

        $this->actingAs($employee, 'web')
            ->put('/password', [
                'current_password' => 'old-password',
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('brand-new-password', $employee->fresh()->employee_password));
    }

    public function test_admin_can_change_own_password(): void
    {
        $admin = Admin::factory()->create(['admin_password' => 'old-password']);

        $this->actingAs($admin, 'admin')
            ->put('/password', [
                'current_password' => 'old-password',
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(Hash::check('brand-new-password', $admin->fresh()->admin_password));
    }

    public function test_current_password_must_be_correct(): void
    {
        $employee = Employee::factory()->create(['employee_password' => 'old-password']);

        $this->actingAs($employee, 'web')
            ->put('/password', [
                'current_password' => 'not-the-password',
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password', $employee->fresh()->employee_password));
    }

    public function test_new_password_must_be_confirmed_and_long_enough(): void
    {
        $employee = Employee::factory()->create(['employee_password' => 'old-password']);

        $this->actingAs($employee, 'web')
            ->put('/password', [
                'current_password' => 'old-password',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_password_page_is_only_for_authenticated_actor(): void
    {
        $this->get('/password')->assertRedirect(route('login'));
    }
}
