<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Department;
use App\Models\Desk;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private Department $department;
    private Zone $zone;
    private Desk $desk;
    private TimeSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $this->department = Department::create([
            'code' => 'IT',
            'name' => 'แผนกเทคโนโลยี',
            'is_active' => true,
        ]);
        $this->employee = User::factory()->create([
            'department_id' => $this->department->id,
        ]);

        $this->zone = Zone::create(['code' => 'Z-A', 'name' => 'โซน A', 'is_active' => true]);
        $this->desk = Desk::create([
            'zone_id' => $this->zone->id,
            'code' => 'A-01',
            'label' => 'โต๊ะ A1',
            'x' => 1,
            'y' => 1,
        ]);
        $this->slot = TimeSlot::create([
            'code' => 'AM',
            'name' => 'ช่วงเช้า 09:00-13:00',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function booking(string $status = Booking::STATUS_CONFIRMED, ?string $date = null, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->employee->id,
            'desk_id' => $this->desk->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => $date ?? Carbon::today()->toDateString(),
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addHours(3),
            'status' => $status,
        ], $overrides));
    }

    public function test_admin_can_view_reports_page(): void
    {
        $this->booking();

        $response = $this->actingAs($this->admin)
            ->get('/admin/reports?from='.Carbon::today()->subDays(6)->toDateString().'&to='.Carbon::today()->toDateString());

        $response->assertOk();
        $response->assertSee('รายงาน');
        $response->assertSee($this->desk->code);
    }

    public function test_admin_can_view_realtime_json(): void
    {
        $booking = $this->booking();

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/dashboard/realtime');

        $response->assertOk()
            ->assertJsonPath('recentActivity.0.booking_id', $booking->id)
            ->assertJsonStructure(['live', 'zoneOccupancy', 'recentActivity', 'server_time']);
    }

    public function test_admin_can_export_reports_csv(): void
    {
        $this->booking();

        $response = $this->actingAs($this->admin)
            ->get('/admin/reports/export?from='.Carbon::today()->subDays(6)->toDateString().'&to='.Carbon::today()->toDateString());

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString($this->desk->code, $csv);
        $this->assertStringContainsString($this->employee->name, $csv);
    }
}
