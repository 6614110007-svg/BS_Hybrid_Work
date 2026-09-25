<?php

namespace Tests\Feature\Admin;

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

        $this->department = Department::create(['code' => 'IT', 'name' => 'ไอที']);
        $this->zone = Zone::create(['code' => 'Z-A', 'name' => 'โซน A']);
        $this->desk = Desk::create(['zone_id' => $this->zone->id, 'code' => 'A-01', 'label' => 'โต๊ะ A1', 'x' => 1, 'y' => 1]);
        $this->slot = TimeSlot::create([
            'code' => 'AM',
            'name' => 'ช่วงเช้า 09:00-13:00',
            'start_time' => '09:00',
            'end_time' => '13:00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->employee = User::factory()->employee()->create(['department_id' => $this->department->id]);
    }

    private function booking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->employee->id,
            'desk_id' => $this->desk->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->toDateString(),
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addHours(3),
            'status' => Booking::STATUS_CHECKED_IN,
            'checked_in_at' => now()->subMinutes(5),
        ], $overrides));
    }

    public function test_admin_can_view_reports_index(): void
    {
        $this->booking();

        $this->actingAs($this->admin)
            ->get('/admin/reports'.'?from='.today()->subDays(6)->toDateString().'&to='.today()->toDateString())
            ->assertOk()
            ->assertSee('รายงาน & สถิติ')
            ->assertSee($this->desk->code);
    }

    public function test_admin_can_view_realtime_json(): void
    {
        $booking = $this->booking();

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/dashboard/realtime');

        $response->assertOk()
            ->assertJsonPath('live.using_now', 1)
            ->assertJsonStructure(['live', 'zoneOccupancy', 'recentActivity', 'server_time'])
            ->assertJsonCount(1, 'recentActivity');

        $activity = $response->json('recentActivity.0');
        $this->assertSame($booking->id, $activity['booking_id']);
    }

    public function test_admin_can_export_reports_csv_with_bom(): void
    {
        $this->booking();

        $response = $this->actingAs($this->admin)
            ->get('/admin/reports/export?from='.today()->subDays(6)->toDateString().'&to='.today()->toDateString());

        $response->assertOk()
            ->assertHeaderContains('content-disposition', 'attachment')
            ->assertHeaderContains('content-type', 'text/csv');

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString($this->desk->code, $csv);
        $this->assertStringContainsString($this->employee->name, $csv);
    }
}
