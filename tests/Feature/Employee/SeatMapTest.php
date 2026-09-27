<?php

namespace Tests\Feature\Employee;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Support\TimeSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_open_seat_map(): void
    {
        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create(['zone_name' => 'โซนทดสอบ']);
        Desk::factory()->count(2)->create(['zone_id' => $zone->zone_id]);

        $this->actingAs($employee, 'web')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('โซนทดสอบ')
            ->assertSee('ค้นหาและจองโต๊ะ');
    }

    public function test_administrator_role_is_redirected_to_admin_dashboard(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_employee_with_administrator_role_cannot_use_employee_dashboard(): void
    {
        $manager = Employee::factory()->administrator()->create();

        $this->actingAs($manager, 'web')
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_status_endpoint_reports_desk_states(): void
    {
        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create();
        $free = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $busy = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $broken = Desk::factory()->maintenance()->create(['zone_id' => $zone->zone_id]);

        $slot = TimeSlot::find('Full Day');

        Booking::factory()->create([
            'employee_id' => $employee->employee_id,
            'desk_id' => $busy->desk_id,
            'booking_date' => now()->toDateString(),
            'time_slot' => $slot->name,
        ]);

        $response = $this->actingAs($employee, 'web')
            ->getJson('/seatmap/status?time_slot='.urlencode($slot->name));

        $response->assertOk();

        $states = collect($response->json('desks'))->keyBy('desk_id');

        $this->assertSame('available', $states[$free->desk_id]['state']);
        $this->assertSame('my_booked', $states[$busy->desk_id]['state']);
        $this->assertSame('maintenance', $states[$broken->desk_id]['state']);
    }

    public function test_unknown_time_slot_is_rejected(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($employee, 'web')
            ->get('/dashboard?time_slot=Night')
            ->assertStatus(422);
    }

    public function test_date_outside_lead_window_is_rejected(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($employee, 'web')
            ->get('/dashboard?date='.now()->subDay()->toDateString())
            ->assertStatus(422);

        $this->actingAs($employee, 'web')
            ->get('/dashboard?date='.now()->addDays((int) config('booking.lead_days') + 5)->toDateString())
            ->assertStatus(422);
    }
}
