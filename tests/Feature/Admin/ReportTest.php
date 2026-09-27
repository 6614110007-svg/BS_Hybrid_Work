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

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_page_shows_summary(): void
    {
        $this->seedBookings();

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get('/admin/reports?date_from='.now()->subDays(2)->toDateString().'&date_to='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertSee('รายงานและสถิติ')
            ->assertSee('ดาวน์โหลด CSV');
    }

    public function test_report_can_be_filtered_by_status(): void
    {
        $this->seedBookings();

        $response = $this->actingAs(Admin::factory()->create(), 'admin')
            ->get('/admin/reports?status='.Booking::STATUS_CHECKED_IN);

        $response->assertOk();
    }

    public function test_csv_export_contains_header_and_row(): void
    {
        $this->seedBookings();

        $response = $this->actingAs(Admin::factory()->create(), 'admin')
            ->get('/admin/reports/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('report-bookings-', (string) $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('วันที่จอง', $csv);
        $this->assertStringContainsString('ชื่อพนักงาน', $csv);
        $this->assertStringContainsString('สมชาย ใจดี', $csv);
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->from('/admin/reports')
            ->get('/admin/reports?date_from=2025-05-10&date_to=2025-05-01')
            ->assertSessionHasErrors('date_to');
    }

    public function test_plain_employee_cannot_open_reports(): void
    {
        $this->actingAs(Employee::factory()->create(), 'web')
            ->get('/admin/reports')
            ->assertRedirect(route('dashboard'));
    }

    private function seedBookings(): void
    {
        $zone = Zone::factory()->create(['zone_name' => 'โซนรายงาน']);
        $department = Department::factory()->create(['department_name' => 'ฝ่ายรายงาน']);
        $desk = Desk::factory()->create(['zone_id' => $zone->zone_id, 'desk_number' => 'R01']);
        $employee = Employee::factory()->create([
            'employee_fullname' => 'สมชาย ใจดี',
            'department_id' => $department->department_id,
        ]);
        $slot = TimeSlot::find('Full Day');

        Booking::factory()->checkedIn()->create([
            'employee_id' => $employee->employee_id,
            'desk_id' => $desk->desk_id,
            'booking_date' => now()->toDateString(),
            'time_slot' => $slot->name,
        ]);
    }
}
