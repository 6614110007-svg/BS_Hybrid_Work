<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Support\TimeSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_account_can_open_dashboard(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('แดชบอร์ดผู้ดูแลระบบ');
    }

    public function test_employee_with_administrator_role_can_open_dashboard(): void
    {
        $this->actingAs(Employee::factory()->administrator()->create(), 'web')
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_plain_employee_is_redirected_to_employee_dashboard(): void
    {
        $this->actingAs(Employee::factory()->create(), 'web')
            ->get('/admin/dashboard')
            ->assertRedirect(route('dashboard'));
    }

    public function test_realtime_endpoint_returns_live_counts(): void
    {
        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create();
        $desk = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $slot = TimeSlot::find('Full Day');

        Booking::factory()->checkedIn()->create([
            'employee_id' => $employee->employee_id,
            'desk_id' => $desk->desk_id,
            'booking_date' => now()->toDateString(),
            'time_slot' => $slot->name,
        ]);

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->getJson('/admin/dashboard/realtime')
            ->assertOk()
            ->assertJsonPath('live.using_now', 1)
            ->assertJsonPath('live.total_usable_desks', 1)
            ->assertJsonPath('live.available_now', 0);
    }

    public function test_admin_can_create_and_delete_department(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post('/admin/departments', ['department_name' => 'ฝ่ายวิจัยและพัฒนา'])
            ->assertRedirect(route('admin.departments.index'));

        $department = Department::where('department_name', 'ฝ่ายวิจัยและพัฒนา')->sole();

        $this->actingAs($admin, 'admin')
            ->delete('/admin/departments/'.$department->department_id)
            ->assertRedirect(route('admin.departments.index'));

        $this->assertSame(0, Department::count());
    }

    public function test_department_with_employees_cannot_be_deleted(): void
    {
        $admin = Admin::factory()->create();
        $department = Department::factory()->create();

        Employee::factory()->count(2)->create(['department_id' => $department->department_id]);

        $this->actingAs($admin, 'admin')
            ->delete('/admin/departments/'.$department->department_id)
            ->assertRedirect(route('admin.departments.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, Department::count());
    }

    public function test_department_name_must_be_unique(): void
    {
        Department::factory()->name('ฝ่ายไอที')->create();

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->from('/admin/departments/create')
            ->post('/admin/departments', ['department_name' => 'ฝ่ายไอที'])
            ->assertSessionHasErrors('department_name');
    }

    public function test_admin_can_create_zone_and_desk(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post('/admin/zones', ['zone_name' => 'โซนทดสอบ'])
            ->assertRedirect(route('admin.zones.index'));

        $zone = Zone::where('zone_name', 'โซนทดสอบ')->sole();

        $this->actingAs($admin, 'admin')
            ->post('/admin/desks', [
                'zone_id' => $zone->zone_id,
                'desk_number' => 'T01',
                'map_position' => '2,3',
                'desk_status' => Desk::STATUS_AVAILABLE,
            ])
            ->assertRedirect(route('admin.desks.index'));

        $desk = Desk::where('desk_number', 'T01')->sole();

        $this->assertSame($zone->zone_id, $desk->zone_id);
        $this->assertSame([2, 3], $desk->position());
        $this->assertSame($zone->zone_id, $desk->zone->zone_id);
    }

    public function test_zone_with_desks_cannot_be_deleted(): void
    {
        $admin = Admin::factory()->create();
        $zone = Zone::factory()->create();

        Desk::factory()->create(['zone_id' => $zone->zone_id]);

        $this->actingAs($admin, 'admin')
            ->delete('/admin/zones/'.$zone->zone_id)
            ->assertRedirect(route('admin.zones.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, Zone::count());
    }

    public function test_admin_can_toggle_desk_maintenance(): void
    {
        $admin = Admin::factory()->create();
        $desk = Desk::factory()->create();

        $this->actingAs($admin, 'admin')
            ->from('/admin/desks')
            ->patch('/admin/desks/'.$desk->desk_id.'/status', ['desk_status' => Desk::STATUS_MAINTENANCE])
            ->assertRedirect('/admin/desks')
            ->assertSessionHas('success');

        $this->assertSame(Desk::STATUS_MAINTENANCE, $desk->fresh()->desk_status);
    }

    public function test_desk_with_active_booking_cannot_be_closed(): void
    {
        $admin = Admin::factory()->create();
        $desk = Desk::factory()->create();

        Booking::factory()->create(['desk_id' => $desk->desk_id]);

        $this->actingAs($admin, 'admin')
            ->from('/admin/desks')
            ->patch('/admin/desks/'.$desk->desk_id.'/status', ['desk_status' => Desk::STATUS_MAINTENANCE])
            ->assertSessionHas('error');

        $this->assertNotSame(Desk::STATUS_MAINTENANCE, $desk->fresh()->desk_status);
    }

    public function test_admin_can_create_employee_with_hashed_password(): void
    {
        $admin = Admin::factory()->create();
        $department = Department::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post('/admin/employees', [
                'employee_fullname' => 'ทดสอบ ระบบ',
                'employee_tel' => '0812345678',
                'employee_email' => 'new.staff@example.test',
                'employee_role' => Employee::ROLE_EMPLOYEE,
                'employee_status' => Employee::STATUS_ACTIVE,
                'department_id' => $department->department_id,
                'password' => 'initial-password',
                'password_confirmation' => 'initial-password',
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee = Employee::where('employee_email', 'new.staff@example.test')->sole();

        $this->assertNotSame('initial-password', $employee->employee_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('initial-password', $employee->employee_password));
    }

    public function test_admin_can_suspend_and_restore_employee(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($admin, 'admin')
            ->from('/admin/employees')
            ->patch('/admin/employees/'.$employee->employee_id.'/status')
            ->assertRedirect('/admin/employees');

        $this->assertSame(Employee::STATUS_INACTIVE, $employee->fresh()->employee_status);

        $this->actingAs($admin, 'admin')
            ->from('/admin/employees')
            ->patch('/admin/employees/'.$employee->employee_id.'/status');

        $this->assertSame(Employee::STATUS_ACTIVE, $employee->fresh()->employee_status);
    }

    public function test_admin_can_cancel_booking_on_behalf_of_employee(): void
    {
        $admin = Admin::factory()->create();
        $booking = Booking::factory()->create();

        $this->actingAs($admin, 'admin')
            ->from('/admin/bookings')
            ->delete('/admin/bookings/'.$booking->booking_id)
            ->assertRedirect('/admin/bookings')
            ->assertSessionHas('success');

        $this->assertSame(Booking::STATUS_EXPIRED, $booking->fresh()->booking_status);
    }

    public function test_admin_cannot_cancel_checked_in_booking(): void
    {
        $admin = Admin::factory()->create();
        $booking = Booking::factory()->checkedIn()->create();

        $this->actingAs($admin, 'admin')
            ->from('/admin/bookings')
            ->delete('/admin/bookings/'.$booking->booking_id)
            ->assertSessionHas('error');

        $this->assertSame(Booking::STATUS_CHECKED_IN, $booking->fresh()->booking_status);
    }
}
