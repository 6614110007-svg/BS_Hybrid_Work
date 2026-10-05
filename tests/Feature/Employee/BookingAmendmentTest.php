<?php

namespace Tests\Feature\Employee;

use App\Models\Booking;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Support\TimeSlot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * การแก้ไขการจอง (Booking Amendment)
 *
 * เงื่อนไข: แก้ได้เฉพาะใบจองที่ยังไม่ถึงเวลาเช็คอิน (สถานะ จองแล้ว / รอเช็คอิน)
 * เปลี่ยนได้ วันที่ / ช่วงเวลา / โต๊ะ โดยต้องตรวจการชนกันที่ฝั่ง Server
 */
class BookingAmendmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserved_booking_shows_edit_button(): void
    {
        [$employee, $booking] = $this->amendContext();

        $this->actingAs($employee, 'web')
            ->get('/bookings')
            ->assertOk()
            ->assertSee('แก้ไขการจอง')
            ->assertSee('data-amend-booking="'.$booking->booking_id.'"', escape: false)
            ->assertSee('ยกเลิก แล้วจองใหม่');
    }

    public function test_checked_in_booking_has_no_edit_button(): void
    {
        [$employee, $booking] = $this->amendContext(Booking::STATUS_CHECKED_IN);

        $this->assertFalse($booking->isAmendable());

        $this->actingAs($employee, 'web')
            ->get('/bookings')
            ->assertOk()
            ->assertDontSee('data-amend-booking="'.$booking->booking_id.'"', escape: false);
    }

    public function test_overdue_reserved_booking_has_no_edit_button(): void
    {
        $this->freezeToBookableToday();

        [$employee, $booking] = $this->amendContext();

        // เลยเวลาเช็คอินแล้ว (เริ่ม 08:00 + grace 60 นาที) ขณะที่ตอนนี้ 09:00 เป็นต้น
        Carbon::setTestNow(Carbon::create(2026, 1, 7, 23, 0, 0));

        $booking->refresh();

        $this->assertTrue($booking->isReserved());
        $this->assertFalse($booking->isAmendable());

        $this->actingAs($employee, 'web')
            ->get('/bookings')
            ->assertOk()
            ->assertDontSee('data-amend-booking="'.$booking->booking_id.'"', escape: false);
    }

    public function test_employee_can_change_time_slot(): void
    {
        [$employee, $booking] = $this->amendContext();

        $slot = TimeSlot::find('Afternoon');

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->patch("/bookings/{$booking->booking_id}", [
                'desk_id' => $booking->desk_id,
                'booking_date' => $booking->booking_date->toDateString(),
                'time_slot' => $slot->name,
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHas('success');

        $booking->refresh();

        $this->assertSame('Afternoon', $booking->time_slot);
        $this->assertSame('13:00', $booking->start_time->format('H:i'));
        $this->assertSame('18:00', $booking->end_time->format('H:i'));
        $this->assertSame(Booking::STATUS_RESERVED, $booking->booking_status);
    }

    public function test_employee_can_change_desk(): void
    {
        [$employee, $booking, $zone, $spare] = $this->amendContext();

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->patch("/bookings/{$booking->booking_id}", [
                'desk_id' => $spare->desk_id,
                'booking_date' => $booking->booking_date->toDateString(),
                'time_slot' => $booking->time_slot,
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHas('success');

        $booking->refresh();

        $this->assertSame($spare->desk_id, $booking->desk_id);

        // โต๊ะเดิมต้องถูกปล่อยกลับเป็นว่าง ไม่ใช่ค้างสถานะ Reserved
        $this->assertSame(Desk::STATUS_AVAILABLE, Desk::whereKey($zone->desks()->first()->desk_id)->value('desk_status'));
    }

    public function test_employee_can_change_date(): void
    {
        [$employee, $booking] = $this->amendContext();

        $newDate = $this->bookableDate(Carbon::today()->addDay());

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->patch("/bookings/{$booking->booking_id}", [
                'desk_id' => $booking->desk_id,
                'booking_date' => $newDate,
                'time_slot' => $booking->time_slot,
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHas('success');

        $this->assertSame($newDate, $booking->refresh()->booking_date->toDateString());
    }

    public function test_keeping_the_same_values_does_not_conflict_with_itself(): void
    {
        [$employee, $booking] = $this->amendContext();

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->patch("/bookings/{$booking->booking_id}", [
                'desk_id' => $booking->desk_id,
                'booking_date' => $booking->booking_date->toDateString(),
                'time_slot' => $booking->time_slot,
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHas('success');

        $this->assertSame(Booking::STATUS_RESERVED, $booking->refresh()->booking_status);
    }

    public function test_cannot_move_to_a_desk_that_is_already_taken(): void
    {
        [$employee, $booking, $zone] = $this->amendContext();

        $taken = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $rival = Employee::factory()->create();

        $slot = TimeSlot::find($booking->time_slot);

        Booking::factory()->create([
            'desk_id' => $taken->desk_id,
            'employee_id' => $rival->employee_id,
            'booking_date' => $booking->booking_date->toDateString(),
            'time_slot' => $slot->name,
            'start_time' => $slot->start,
            'end_time' => $slot->end,
        ]);

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->patch("/bookings/{$booking->booking_id}", [
                'desk_id' => $taken->desk_id,
                'booking_date' => $booking->booking_date->toDateString(),
                'time_slot' => $slot->name,
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHasErrors('desk_id');

        $this->assertSame($booking->desk_id, $booking->refresh()->desk_id);
    }

    public function test_cannot_double_book_the_employee_in_the_same_slot(): void
    {
        [$employee, $booking, $zone, $spare] = $this->amendContext();

        $slot = TimeSlot::find($booking->time_slot);

        // พนักงานคนนี้มีอีกใบจองในวัน/ช่วงเดียวกันอยู่แล้ว
        Booking::factory()->create([
            'desk_id' => $spare->desk_id,
            'employee_id' => $employee->employee_id,
            'booking_date' => $booking->booking_date->toDateString(),
            'time_slot' => $slot->name,
            'start_time' => $slot->start,
            'end_time' => $slot->end,
        ]);

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->patch("/bookings/{$booking->booking_id}", [
                'desk_id' => $spare->desk_id,
                'booking_date' => $booking->booking_date->toDateString(),
                'time_slot' => $slot->name,
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHasErrors('desk_id');
    }

    public function test_cannot_move_booking_to_a_maintenance_desk(): void
    {
        [$employee, $booking, $zone] = $this->amendContext();

        $broken = Desk::factory()->maintenance()->create(['zone_id' => $zone->zone_id]);

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->patch("/bookings/{$booking->booking_id}", [
                'desk_id' => $broken->desk_id,
                'booking_date' => $booking->booking_date->toDateString(),
                'time_slot' => $booking->time_slot,
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHasErrors('desk_id');
    }

    public function test_cannot_move_booking_to_a_non_bookable_date(): void
    {
        [$employee, $booking] = $this->amendContext();

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->patch("/bookings/{$booking->booking_id}", [
                'desk_id' => $booking->desk_id,
                'booking_date' => '2026-04-13', // วันสงกรานต์
                'time_slot' => $booking->time_slot,
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHasErrors('booking_date');
    }

    public function test_employee_cannot_amend_someone_elses_booking(): void
    {
        [, $booking] = $this->amendContext();

        $intruder = Employee::factory()->create();

        $this->actingAs($intruder, 'web')
            ->patch("/bookings/{$booking->booking_id}", [
                'desk_id' => $booking->desk_id,
                'booking_date' => $booking->booking_date->toDateString(),
                'time_slot' => $booking->time_slot,
            ])
            ->assertForbidden();
    }

    public function test_checked_in_booking_cannot_be_amended_via_http(): void
    {
        [$employee, $booking] = $this->amendContext(Booking::STATUS_CHECKED_IN);

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->patch("/bookings/{$booking->booking_id}", [
                'desk_id' => $booking->desk_id,
                'booking_date' => $booking->booking_date->toDateString(),
                'time_slot' => 'Afternoon',
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHas('error');

        $this->assertSame(Booking::STATUS_CHECKED_IN, $booking->refresh()->booking_status);
    }

    public function test_cannot_rebook_using_rebook_flag_when_overdue(): void
    {
        $this->freezeToBookableToday();

        [$employee, $booking] = $this->amendContext();

        Carbon::setTestNow(Carbon::create(2026, 1, 7, 23, 0, 0));

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->patch("/bookings/{$booking->booking_id}", [
                'desk_id' => $booking->desk_id,
                'booking_date' => $booking->booking_date->toDateString(),
                'time_slot' => 'Afternoon',
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHas('error');
    }

    public function test_cancel_and_rebook_redirects_to_the_desk_picker(): void
    {
        [$employee, $booking] = $this->amendContext();

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->delete("/bookings/{$booking->booking_id}", ['rebook' => 1])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertSame(Booking::STATUS_EXPIRED, $booking->refresh()->booking_status);
    }

    public function test_plain_cancel_returns_to_the_booking_list(): void
    {
        [$employee, $booking] = $this->amendContext();

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->delete("/bookings/{$booking->booking_id}")
            ->assertRedirect('/bookings');

        $this->assertSame(Booking::STATUS_EXPIRED, $booking->refresh()->booking_status);
    }

    public function test_maintenance_desks_are_not_offered_in_the_amend_modal(): void
    {
        [$employee, , $zone] = $this->amendContext();

        $broken = Desk::factory()->maintenance()->create([
            'zone_id' => $zone->zone_id,
            'desk_number' => 'ZZ-99',
        ]);

        $html = $this->actingAs($employee, 'web')
            ->get('/bookings')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('ZZ-99', $html);
        $this->assertStringContainsString('data-update-template', $html);
        $this->assertSame('ZZ-99', $broken->desk_number);
    }

    public function test_amend_modal_is_not_shown_to_guests(): void
    {
        $this->get('/bookings')->assertRedirect(route('login'));
    }

    public function test_failed_amendment_reopens_the_modal_with_the_values_the_user_typed(): void
    {
        [$employee, $booking, $zone, $spare] = $this->amendContext();

        $taken = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $rival = Employee::factory()->create();
        $slot = TimeSlot::find('Afternoon');
        $newDate = $this->bookableDate(Carbon::today()->addDay());

        Booking::factory()->create([
            'desk_id' => $taken->desk_id,
            'employee_id' => $rival->employee_id,
            'booking_date' => $newDate,
            'time_slot' => $slot->name,
            'start_time' => $slot->start,
            'end_time' => $slot->end,
        ]);

        // ต้องใช้ followingRedirects ในคำขอเดียว
        // เพราะการอ่าน flash session ก่อนจะทำให้ error ไม่ถูก render ในคำขอถัดไป
        $html = $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->followingRedirects()
            ->patch("/bookings/{$booking->booking_id}", [
                'desk_id' => $taken->desk_id,
                'booking_date' => $newDate,
                'time_slot' => $slot->name,
            ])
            ->assertOk()
            ->assertSee('โต๊ะนี้ถูกจองในช่วงเวลานี้แล้ว')
            ->getContent();

        // modal ต้องเปิดค้างไว้ พร้อมค่าที่ผู้ใช้กรอก ไม่ใช่กลับมาแล้วปิดหาย
        $this->assertStringContainsString('data-amend-reopen="1"', $html);
        $this->assertStringContainsString('amendOpen: reopen !== null', $html);
        $this->assertStringContainsString($newDate, $html);

        // ค่าเดิมของใบจองยังไม่ถูกแก้
        $this->assertSame($booking->desk_id, $booking->refresh()->desk_id);
        $this->assertNotSame($spare->desk_id, $booking->desk_id);
    }

    public function test_failed_amendment_reports_which_booking_and_keeps_the_input(): void
    {
        [$employee, $booking, $zone] = $this->amendContext();

        $taken = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $rival = Employee::factory()->create();
        $slot = TimeSlot::find('Afternoon');
        $newDate = $this->bookableDate(Carbon::today()->addDay());

        Booking::factory()->create([
            'desk_id' => $taken->desk_id,
            'employee_id' => $rival->employee_id,
            'booking_date' => $newDate,
            'time_slot' => $slot->name,
            'start_time' => $slot->start,
            'end_time' => $slot->end,
        ]);

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->patch("/bookings/{$booking->booking_id}", [
                'desk_id' => $taken->desk_id,
                'booking_date' => $newDate,
                'time_slot' => $slot->name,
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHasErrors('desk_id')
            // เก็บ id ของใบจองไว้ เพื่อให้หน้า /bookings เปิด modal กลับมาตัวที่แก้
            ->assertSessionHas('amend_booking_id', $booking->booking_id)
            // เก็บค่าที่ผู้ใช้กรอกไว้ กลับไปเติมในฟอร์ม
            ->assertSessionHasInput('desk_id', (string) $taken->desk_id)
            ->assertSessionHasInput('booking_date', $newDate)
            ->assertSessionHasInput('time_slot', 'Afternoon');
    }

    public function test_failed_validation_also_reopens_the_modal(): void
    {
        [$employee, $booking] = $this->amendContext();

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->patch("/bookings/{$booking->booking_id}", [
                'desk_id' => $booking->desk_id,
                'booking_date' => '2026-04-13', // วันหยุด
                'time_slot' => $booking->time_slot,
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHasErrors('booking_date')
            ->assertSessionHas('amend_booking_id', $booking->booking_id);
    }

    public function test_modal_stays_closed_when_there_is_nothing_to_fix(): void
    {
        $html = $this->actingAs(Employee::factory()->create(), 'web')
            ->get('/bookings')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-amend-reopen="0"', $html);
    }

    /**
     * @return array{0: Employee, 1: Booking, 2: Zone, 3: Desk}
     */
    private function amendContext(string $status = Booking::STATUS_RESERVED): array
    {
        $zone = Zone::factory()->create();
        $desk = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $spare = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $employee = Employee::factory()->create();

        $slot = TimeSlot::find('Full Day');

        $booking = Booking::factory()->create([
            'desk_id' => $desk->desk_id,
            'employee_id' => $employee->employee_id,
            'booking_date' => $this->bookableDate(),
            'time_slot' => $slot->name,
            'start_time' => $slot->start,
            'end_time' => $slot->end,
            'booking_status' => $status,
        ]);

        return [$employee, $booking, $zone, $spare];
    }
}
