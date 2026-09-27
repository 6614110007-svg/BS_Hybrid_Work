<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_employee_page_renders(): void
    {
        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create();
        $desk = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $booking = Booking::factory()->create([
            'employee_id' => $employee->employee_id,
            'desk_id' => $desk->desk_id,
        ]);

        $this->actingAs($employee, 'web');

        foreach ([
            '/dashboard',
            '/seatmap/status',
            '/bookings',
            '/password',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        // หน้าเช็คอินเปิดได้เฉพาะช่วงเวลาเช็คอินของใบจอง
        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $this->get("/bookings/{$booking->booking_id}/checkin")->assertOk();
    }

    public function test_every_admin_page_renders(): void
    {
        Http::fake([
            '*' => Http::response(['signedURL' => '/storage/v1/object/sign/checkin-photos/checkins/x.jpg?token=x'], 200),
        ]);

        $admin = Admin::factory()->create();
        $department = Department::factory()->create();
        $zone = Zone::factory()->create();
        $desk = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $employee = Employee::factory()->create(['department_id' => $department->department_id]);
        $booking = Booking::factory()->create([
            'desk_id' => $desk->desk_id,
            'booking_status' => Booking::STATUS_CHECKED_IN,
            'actual_checkin_time' => now(),
            'checkin_photo' => 'checkins/x.jpg',
        ]);

        $this->actingAs($admin, 'admin');

        foreach ([
            '/admin/dashboard',
            '/admin/dashboard/realtime',
            '/admin/reports',
            '/admin/bookings',
            '/admin/departments',
            '/admin/departments/create',
            "/admin/departments/{$department->department_id}/edit",
            '/admin/zones',
            '/admin/zones/create',
            "/admin/zones/{$zone->zone_id}/edit",
            '/admin/desks',
            '/admin/desks/create',
            "/admin/desks/{$desk->desk_id}/edit",
            '/admin/employees',
            '/admin/employees/create',
            "/admin/employees/{$employee->employee_id}/edit",
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get("/bookings/{$booking->booking_id}/photo")
            ->assertRedirect();
    }

    public function test_guest_sees_login_page(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/')->assertRedirect('/login');
    }
}
