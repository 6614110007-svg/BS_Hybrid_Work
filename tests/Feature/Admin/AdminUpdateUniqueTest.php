<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Department;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * กันไม่ให้ validation unique ของ admin resource อ้างคีย์หลักชื่อ "id"
 * ซึ่งไม่มีอยู่จริง เพราะทุกตารางใช้คีย์หลักแบบ string เช่น zone_id / employee_id
 *
 * อาการเดิม: หน้าแก้ไขโต๊ะ/โซน/แผนก/พนักงาน ตอบ 500 ทันที
 * เพราะ Rule::unique()->ignore('ZON00000001') คงค่า idColumn ไว้ที่ "id"
 */
class AdminUpdateUniqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_update_keeps_its_own_name_without_sql_error(): void
    {
        $admin = Admin::factory()->create();
        $zone = Zone::factory()->name('โซนติดหน้าต่าง')->create();

        $this->actingAs($admin, 'admin')
            ->from("/admin/zones/{$zone->zone_id}/edit")
            ->put("/admin/zones/{$zone->zone_id}", ['zone_name' => 'โซนติดหน้าต่าง'])
            ->assertRedirect(route('admin.zones.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('โซนติดหน้าต่าง', $zone->fresh()->zone_name);
    }

    public function test_zone_update_still_rejects_a_name_used_by_another_zone(): void
    {
        $admin = Admin::factory()->create();
        $zone = Zone::factory()->name('โซนเงียบ')->create();
        Zone::factory()->name('โซนติดหน้าต่าง')->create();

        $this->actingAs($admin, 'admin')
            ->from("/admin/zones/{$zone->zone_id}/edit")
            ->put("/admin/zones/{$zone->zone_id}", ['zone_name' => 'โซนติดหน้าต่าง'])
            ->assertSessionHasErrors('zone_name');

        $this->assertSame('โซนเงียบ', $zone->fresh()->zone_name);
    }

    public function test_department_update_keeps_its_own_name_without_sql_error(): void
    {
        $admin = Admin::factory()->create();
        $department = Department::factory()->name('ฝ่ายไอที')->create();

        $this->actingAs($admin, 'admin')
            ->from("/admin/departments/{$department->department_id}/edit")
            ->put("/admin/departments/{$department->department_id}", ['department_name' => 'ฝ่ายไอที'])
            ->assertRedirect(route('admin.departments.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('ฝ่ายไอที', $department->fresh()->department_name);
    }

    public function test_department_update_still_rejects_a_name_used_by_another_department(): void
    {
        $admin = Admin::factory()->create();
        $department = Department::factory()->name('ฝ่ายการเงิน')->create();
        Department::factory()->name('ฝ่ายไอที')->create();

        $this->actingAs($admin, 'admin')
            ->from("/admin/departments/{$department->department_id}/edit")
            ->put("/admin/departments/{$department->department_id}", ['department_name' => 'ฝ่ายไอที'])
            ->assertSessionHasErrors('department_name');
    }

    public function test_desk_update_keeps_its_own_number_without_sql_error(): void
    {
        $admin = Admin::factory()->create();
        $desk = Desk::factory()->number('A108')->create();

        $this->actingAs($admin, 'admin')
            ->from("/admin/desks/{$desk->desk_id}/edit")
            ->put("/admin/desks/{$desk->desk_id}", [
                'zone_id' => $desk->zone_id,
                'desk_number' => 'A108',
                'map_position' => '2,3',
                'desk_status' => Desk::STATUS_AVAILABLE,
            ])
            ->assertRedirect(route('admin.desks.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('A108', $desk->fresh()->desk_number);
    }

    public function test_desk_update_still_rejects_a_number_used_by_another_desk(): void
    {
        $admin = Admin::factory()->create();
        $desk = Desk::factory()->number('A108')->create();
        Desk::factory()->number('A109')->create();

        $this->actingAs($admin, 'admin')
            ->from("/admin/desks/{$desk->desk_id}/edit")
            ->put("/admin/desks/{$desk->desk_id}", [
                'zone_id' => $desk->zone_id,
                'desk_number' => 'A109',
                'map_position' => '2,3',
                'desk_status' => Desk::STATUS_AVAILABLE,
            ])
            ->assertSessionHasErrors('desk_number');
    }

    public function test_employee_update_keeps_its_own_email_without_sql_error(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::factory()->email('employee1@bs-hybrid.test')->create();

        $this->actingAs($admin, 'admin')
            ->from("/admin/employees/{$employee->employee_id}/edit")
            ->put("/admin/employees/{$employee->employee_id}", [
                'employee_fullname' => 'ชื่อใหม่',
                'employee_tel' => '0812345678',
                'employee_email' => 'employee1@bs-hybrid.test',
                'employee_role' => Employee::ROLE_EMPLOYEE,
                'employee_status' => Employee::STATUS_ACTIVE,
                'department_id' => $employee->department_id,
            ])
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('ชื่อใหม่', $employee->fresh()->employee_fullname);
    }

    public function test_employee_update_still_rejects_an_email_used_by_another_employee(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::factory()->email('employee1@bs-hybrid.test')->create();
        Employee::factory()->email('employee2@bs-hybrid.test')->create();

        $this->actingAs($admin, 'admin')
            ->from("/admin/employees/{$employee->employee_id}/edit")
            ->put("/admin/employees/{$employee->employee_id}", [
                'employee_fullname' => 'ชื่อใหม่',
                'employee_tel' => '0812345678',
                'employee_email' => 'employee2@bs-hybrid.test',
                'employee_role' => Employee::ROLE_EMPLOYEE,
                'employee_status' => Employee::STATUS_ACTIVE,
                'department_id' => $employee->department_id,
            ])
            ->assertSessionHasErrors('employee_email');
    }
}