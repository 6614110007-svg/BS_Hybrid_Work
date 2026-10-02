<?php

namespace Tests\Feature\Employee;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Services\Analytics;
use App\Support\OptionCache;
use App\Support\TimeSlot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_picker_calendar_blocks_weekends_and_holidays(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAs($employee, 'web')->get('/dashboard');

        $response->assertOk();

        $picker = $response->viewData('bookingPicker');

        $this->assertNotEmpty($picker['holidays']);
        $this->assertContains('2026-04-13', array_keys($picker['holidays']));
        $this->assertSame(Carbon::today()->toDateString(), $picker['minDate']);
        $this->assertNotEmpty($picker['maxDate']);
        $this->assertLessThanOrEqual(now()->addDays((int) config('booking.lead_days')), $picker['maxDate']);
    }

    public function test_calendar_is_rendered_for_booking(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAs($employee, 'web')->get('/dashboard');

        $response->assertOk()
            ->assertSee('bookingFilters')
            ->assertSee('data-booking-filters', false)
            ->assertSee('x-ref="datePicker"', false)
            ->assertSee('booking-pill', false)
            ->assertSee('booking-legend__disabled', false)
            ->assertSee('วันที่ต้องการเข้าใช้งาน')
            ->assertSee('คลิกเพื่อเลือกวันที่')
            ->assertSee('คลิกที่ช่องหรือไอคอนปฏิทินเพื่อเลือก ช่วงวันที่ '.(int) config('booking.lead_days').' วันข้างหน้า')
            ->assertSee('booking-date__icon', false)
            ->assertSee('📅', false);

        // ช่อง "วันที่เข้าทำงาน" เลือกได้ด้วยการคลิกในปฏิทินอย่างเดียว จึงต้องเป็น readonly
        $this->assertSame(
            1,
            preg_match('/<input[^>]*id="booking-date"[^>]*>/', $response->getContent(), $matches),
            'ต้องมี input ของช่องวันที่จอง'
        );
        $this->assertStringContainsString('readonly', $matches[0]);
    }

    public function test_default_slot_switches_to_afternoon_after_noon(): void
    {
        $employee = Employee::factory()->create();
        $this->travelTo(Carbon::today()->setTime(15, 0));

        $response = $this->actingAs($employee, 'web')->get('/dashboard');

        $this->assertSame('Afternoon', $response->viewData('slot')->name);
        $this->assertSame('Afternoon', $response->viewData('bookingPicker')['slot']);

        $this->actingAs($employee, 'web')
            ->get('/dashboard?time_slot=Full%20Day')
            ->assertOk()
            ->assertViewHas('slot', fn ($slot) => $slot->name === 'Full Day');
    }

    public function test_default_slot_is_first_selectable_before_noon(): void
    {
        $employee = Employee::factory()->create();
        $this->travelTo(Carbon::today()->setTime(9, 0));

        $response = $this->actingAs($employee, 'web')->get('/dashboard');

        $this->assertSame('Full Day', $response->viewData('slot')->name);
    }

    public function test_dashboard_marks_the_ajax_region_for_partial_refresh(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($employee, 'web')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-ajax-region', false)
            ->assertSee('data-seatmap-body', false)
            ->assertSee('data-loading-button', false);
    }

    public function test_booking_confirmation_modal_is_rendered_with_booking_form_metadata(): void
    {
        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create();
        Desk::factory()->create(['zone_id' => $zone->zone_id, 'desk_number' => 'A01']);

        $this->actingAs($employee, 'web')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('bookingConfirm', false)
            ->assertSee('data-booking-form', false)
            ->assertSee('data-desk-number', false)
            ->assertSee('data-zone-name', false)
            ->assertSee('data-booking-date', false)
            ->assertSee('data-time-slot', false)
            ->assertSee('ยืนยันการจอง', false);
    }

    public function test_each_desk_card_exposes_state_classes_for_live_updates(): void
    {
        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create();
        Desk::factory()->create(['zone_id' => $zone->zone_id, 'desk_number' => 'A01']);

        $html = $this->actingAs($employee, 'web')
            ->getJson('/seatmap/partial?time_slot=Full%20Day')
            ->json('html');

        $this->assertStringContainsString('data-state-dot', $html);
        $this->assertStringContainsString('data-dot-class=', $html);
        $this->assertStringContainsString('data-card-class=', $html);
        $this->assertStringContainsString('data-state="available"', $html);
    }

    public function test_partial_endpoint_returns_only_the_seat_map_html(): void
    {
        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create(['zone_name' => 'โซน AJAX']);
        $desk = Desk::factory()->create(['zone_id' => $zone->zone_id, 'desk_number' => 'A01']);

        $response = $this->actingAs($employee, 'web')
            ->getJson('/seatmap/partial?time_slot=Full%20Day&zone_id='.$zone->zone_id);

        $response->assertOk()
            ->assertJsonStructure(['html', 'date', 'time_slot', 'zone_id'])
            ->assertJsonPath('zone_id', $zone->zone_id)
            ->assertJsonPath('time_slot', 'Full Day');

        $html = $response->json('html');

        $this->assertStringContainsString('โซน AJAX', $html);
        $this->assertStringContainsString('A01', $html);
        $this->assertStringContainsString('data-desk-id="'.$desk->desk_id.'"', $html);
        // partial ต้องเป็นเฉพาะผังโต๊ะ ไม่ลากหัวหน้าเว็บมาด้วย
        $this->assertStringNotContainsString('<!DOCTYPE html>', $html);
        $this->assertStringNotContainsString('layouts.app', $html);
    }

    public function test_partial_endpoint_filters_by_zone(): void
    {
        $employee = Employee::factory()->create();
        $wanted = Zone::factory()->create(['zone_name' => 'โซนที่เลือก']);
        $other = Zone::factory()->create(['zone_name' => 'โซนที่ไม่เลือก']);
        Desk::factory()->create(['zone_id' => $wanted->zone_id, 'desk_number' => 'B01']);
        Desk::factory()->create(['zone_id' => $other->zone_id, 'desk_number' => 'B02']);

        $html = $this->actingAs($employee, 'web')
            ->getJson('/seatmap/partial?time_slot=Full%20Day&zone_id='.$wanted->zone_id)
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('โซนที่เลือก', $html);
        $this->assertStringNotContainsString('โซนที่ไม่เลือก', $html);
    }

    public function test_partial_endpoint_keeps_hidden_booking_fields_in_sync_with_the_date(): void
    {
        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create();
        Desk::factory()->create(['zone_id' => $zone->zone_id]);

        $date = Carbon::today()->addDay()->toDateString();

        $html = $this->actingAs($employee, 'web')
            ->getJson('/seatmap/partial?date='.$date.'&time_slot=Full%20Day')
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('name="booking_date" value="'.$date.'"', $html);
        $this->assertStringContainsString('name="time_slot" value="Full Day"', $html);
    }

    public function test_partial_endpoint_rejects_date_outside_the_window(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($employee, 'web')
            ->getJson('/seatmap/partial?date='.now()->subDay()->toDateString())
            ->assertStatus(422);
    }

    public function test_employee_cannot_open_partial_endpoint(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->getJson('/seatmap/partial')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_zone_options_are_cached_as_plain_arrays_not_models(): void
    {
        Zone::factory()->create(['zone_name' => 'โซนที่ต้องหาเจอ']);
        OptionCache::zones();

        // cache ต้องเก็บ array ข้อมูลดิบ ไม่ใช่ Eloquent model
        // เพราะ cache store แบบ file/database จะ unserialize model
        // กลายเป็น __PHP_Incomplete_Class แล้วทำให้ dropdown ขึ้น 500
        $raw = Cache::get('options:zones');

        $this->assertIsArray($raw);
        $this->assertContainsOnly('array', $raw);
        $this->assertSame(['zone_id', 'zone_name'], array_keys($raw[0]));

        $options = OptionCache::zones();

        $this->assertContainsOnly('object', $options);
        $this->assertTrue($options->contains(fn ($zone) => $zone->zone_name === 'โซนที่ต้องหาเจอ'));
    }

    public function test_zone_dropdown_renders_after_being_cached(): void
    {
        $employee = Employee::factory()->create();
        Zone::factory()->create(['zone_name' => 'โซนแคชแล้วยังต้องโชว์']);

        // ครั้งแรกเติม cache
        $this->actingAs($employee, 'web')->get('/dashboard')->assertOk()->assertSee('โซนแคชแล้วยังต้องโชว์');

        // ครั้งที่สองอ่านจาก cache ต้องยังเรนเดอร์ dropdown ได้ (ไม่ 500)
        $this->actingAs($employee, 'web')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('โซนแคชแล้วยังต้องโชว์')
            ->assertSee('zone_id', false);
    }

    public function test_flushing_option_cache_also_drops_the_dashboard_totals(): void
    {
        // ยอดรวมบนหน้า admin dashboard นับจากตารางเดียวกับ dropdown
        // ถ้าไม่ล้างพร้อมกัน ผู้ดูแลจะเห็นยอดเก่าหลังเพิ่ม/ลบข้อมูล
        Cache::put('admin:dashboard:totals', ['total_zones' => 1], now()->addMinutes(5));
        Cache::put('options:zones', [['zone_id' => 'Z1']], now()->addMinutes(5));

        OptionCache::flush();

        $this->assertNull(Cache::get('admin:dashboard:totals'));
        $this->assertNull(Cache::get('options:zones'));
    }

    public function test_analytics_zone_structure_cache_stores_plain_arrays(): void
    {
        $zone = Zone::factory()->create();
        Desk::factory()->create(['zone_id' => $zone->zone_id, 'desk_status' => Desk::STATUS_AVAILABLE]);
        Desk::factory()->create(['zone_id' => $zone->zone_id, 'desk_status' => Desk::STATUS_MAINTENANCE]);

        // โครงสร้างโซนถูก cache เป็น array เหมือน dropdown
        // ถ้าเก็บเป็น Eloquent collection จะกลายเป็น __PHP_Incomplete_Class ตอนอ่านกลับ
        $analytics = app(Analytics::class);
        $analytics->zoneOccupancy(now()->toDateString());
        $raw = Cache::get('analytics:zones-with-desks');

        $this->assertIsArray($raw);
        $this->assertContainsOnly('array', $raw);
    }
}
