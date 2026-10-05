<?php

namespace Tests\Feature\Employee;

use App\Models\Booking;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Support\TimeSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Zone Header บนหน้าเลือกจองโต๊ะ (/dashboard)
 *
 * ต้องแสดงรูปปกโซน คำอธิบายบรรยากาศ และจำนวนโต๊ะที่ว่างจริง ณ ขณะนั้น
 */
class ZoneHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_zone_description_and_live_desk_counts(): void
    {
        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create([
            'zone_name' => 'โซนวิวทะเล',
            'zone_description' => 'ริมทะเล มีแสงธรรมชาติ เหมาะกับการทำงานที่ต้องโฟกัส',
        ]);

        Desk::factory()->count(3)->create(['zone_id' => $zone->zone_id]);

        $this->actingAs($employee, 'web')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('โซนวิวทะเล')
            ->assertSee('ริมทะเล มีแสงธรรมชาติ เหมาะกับการทำงานที่ต้องโฟกัส')
            ->assertSee('3 โต๊ะ · ใช้งานได้ 3');
    }

    public function test_dashboard_shows_zone_thumbnail_uploaded_by_admin(): void
    {
        Storage::fake('public');

        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create(['zone_name' => 'โซนมีรูป']);

        Storage::disk('public')->put('zones/test-zone.png', 'fake-image-bytes');
        $zone->forceFill(['zone_image' => 'zones/test-zone.png'])->save();

        $this->actingAs($employee, 'web')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('โซนมีรูป')
            ->assertSee('storage/zones/test-zone.png', escape: false);
    }

    public function test_dashboard_shows_placeholder_when_zone_has_no_image(): void
    {
        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create(['zone_name' => 'โซนไม่มีรูป']);

        Desk::factory()->count(2)->create(['zone_id' => $zone->zone_id]);

        $this->actingAs($employee, 'web')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('โซนไม่มีรูป')
            ->assertSee('ยังไม่มีรูปประจำโซน');
    }

    public function test_available_count_excludes_booked_and_maintenance_desks(): void
    {
        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create(['zone_name' => 'โซนนับจำนวน']);

        $free = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $taken = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        Desk::factory()->maintenance()->create(['zone_id' => $zone->zone_id]);
        Desk::factory()->maintenance()->create(['zone_id' => $zone->zone_id]);

        $slot = TimeSlot::find('Full Day');

        Booking::factory()->create([
            'desk_id' => $taken->desk_id,
            'employee_id' => $employee->employee_id,
            'booking_date' => $this->bookableDate(),
            'time_slot' => $slot->name,
            'start_time' => $slot->start,
            'end_time' => $slot->end,
        ]);

        $this->actingAs($employee, 'web')
            ->get('/dashboard?time_slot='.urlencode($slot->name).'&date='.$this->bookableDate())
            ->assertOk()
            // 4 โต๊ะทั้งหมด แต่ใช้งานได้จริง 1 ตัว (ถูกจอง 1 + ปิดซ่อม 2)
            ->assertSee('4 โต๊ะ · ใช้งานได้ 1');

        $this->assertSame($free->desk_id, Desk::whereKey($free->desk_id)->value('desk_id'));
    }

    public function test_status_endpoint_returns_zone_summaries_for_real_time_updates(): void
    {
        $employee = Employee::factory()->create();
        $zone = Zone::factory()->create(['zone_name' => 'โซนเรียลไทม์']);

        Desk::factory()->count(2)->create(['zone_id' => $zone->zone_id]);
        Desk::factory()->maintenance()->create(['zone_id' => $zone->zone_id]);

        $response = $this->actingAs($employee, 'web')
            ->getJson('/seatmap/status')
            ->assertOk();

        $summaries = collect($response->json('zones'))->keyBy('zone_id');

        $this->assertTrue($summaries->has($zone->zone_id));
        $this->assertSame(3, $summaries[$zone->zone_id]['total']);
        $this->assertSame(2, $summaries[$zone->zone_id]['available']);
        $this->assertSame('โซนเรียลไทม์', $summaries[$zone->zone_id]['zone_name']);
    }

    public function test_zone_summary_counts_all_zones_even_when_filtered_by_zone(): void
    {
        $employee = Employee::factory()->create();

        $first = Zone::factory()->create(['zone_name' => 'โซนก']);
        $second = Zone::factory()->create(['zone_name' => 'โซนข']);

        Desk::factory()->count(2)->create(['zone_id' => $first->zone_id]);
        Desk::factory()->count(3)->create(['zone_id' => $second->zone_id]);

        $response = $this->actingAs($employee, 'web')
            ->getJson('/seatmap/status?zone_id='.$first->zone_id)
            ->assertOk();

        $summaries = collect($response->json('zones'))->keyBy('zone_id');

        $this->assertSame(2, $summaries[$first->zone_id]['total']);
        $this->assertSame(3, $summaries[$second->zone_id]['total']);
        // โต๊ะที่ส่งกลับถูกกรองเฉพาะโซนที่เลือก แต่ summary ยังครบทุกโซน
        $this->assertCount(1, $response->json('desks') ? array_unique(array_column($response->json('desks'), 'zone_id')) : []);
    }
}
