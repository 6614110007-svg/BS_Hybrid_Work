<?php

namespace Tests\Feature\Employee;

use App\Models\Booking;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Same-Day Walk-in — พนักงานกดจองโต๊ะในวันเดียวกันหลังเวลาเริ่มรอบแล้ว
 * ระบบต้องนับเวลาเช็คอิน 60 นาทีจาก "เวลาที่กดจอง" ไม่ใช่จากเวลาเริ่มรอบ
 */
class SameDayWalkInTest extends TestCase
{
    use RefreshDatabase;

    public function test_walkin_booking_expires_60_minutes_after_booking_time(): void
    {
        $date = $this->walkInDate();
        $this->travelTo($date->copy()->setTime(15, 0));

        [$employee, $desk] = $this->context();

        $this->actingAs($employee, 'web')
            ->from('/dashboard')
            ->post('/bookings', [
                'desk_id' => $desk->desk_id,
                'booking_date' => $date->toDateString(),
                'time_slot' => 'Afternoon',
            ])
            ->assertSessionHasNoErrors();

        $booking = Booking::sole();

        // start_time ยังคงเป็นเวลาเริ่มรอบ (13:00) ตาม Schema เดิม
        $this->assertSame('13:00', $booking->start_time->format('H:i'));
        // แต่เวลาหมดอายุต้องนับจากเวลาที่กดจอง (15:00 + 60 นาที)
        $this->assertSame('16:00', $booking->checkinDeadline()->format('H:i'));
    }

    public function test_walkin_booking_is_not_auto_cancelled_immediately(): void
    {
        $date = $this->walkInDate();
        $this->travelTo($date->copy()->setTime(15, 0));
        [$employee, $desk] = $this->context();
        $this->bookAfternoon($employee, $desk, $date);

        // 5 นาทีหลังกดจอง ระบบต้องยังไม่ยกเลิกใบจอง
        $this->travelTo($date->copy()->setTime(15, 5));
        $this->artisan('bookings:auto-cancel')->assertSuccessful();

        $this->assertSame(Booking::STATUS_RESERVED, Booking::sole()->booking_status);
    }

    public function test_walkin_booking_is_cancelled_after_the_grace_period(): void
    {
        $date = $this->walkInDate();
        $this->travelTo($date->copy()->setTime(15, 0));
        [$employee, $desk] = $this->context();
        $this->bookAfternoon($employee, $desk, $date);

        $this->travelTo($date->copy()->setTime(16, 1));
        $this->artisan('bookings:auto-cancel')
            ->expectsOutputToContain('ยกเลิกใบจองที่เลยกำหนด 1 รายการ')
            ->assertSuccessful();

        $this->assertSame(Booking::STATUS_EXPIRED, Booking::sole()->booking_status);
    }

    public function test_employee_can_checkin_inside_the_walkin_window(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response(['Key' => 'checkins/x.jpg'], 200)]);

        $date = $this->walkInDate();
        $this->travelTo($date->copy()->setTime(15, 0));
        [$employee, $desk] = $this->context();
        $this->bookAfternoon($employee, $desk, $date);
        $booking = Booking::sole();

        // ยังไม่ถึงเวลาหมดอายุ ต้องเช็คอินได้
        $this->travelTo($date->copy()->setTime(15, 45));

        $this->actingAs($employee, 'web')
            ->get('/bookings/'.$booking->booking_id.'/checkin')
            ->assertOk()
            ->assertSee('16:00')
            ->assertSee('ใบจองนี้ทำรายการหลังเวลาเริ่มรอบ');

        $this->actingAs($employee, 'web')
            ->post('/bookings/'.$booking->booking_id.'/checkin', [
                'photo' => UploadedFile::fake()->create('selfie.jpg', 20, 'image/jpeg'),
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHas('success');

        $this->assertSame(Booking::STATUS_CHECKED_IN, $booking->fresh()->booking_status);
    }

    public function test_booking_before_slot_start_still_expires_from_slot_start(): void
    {
        $date = Carbon::parse($this->bookableDate());
        $this->travelTo($date->copy()->setTime(7, 0));

        [$employee, $desk] = $this->context();

        $this->actingAs($employee, 'web')
            ->from('/dashboard')
            ->post('/bookings', [
                'desk_id' => $desk->desk_id,
                'booking_date' => $date->toDateString(),
                'time_slot' => 'Full Day',
            ])
            ->assertSessionHasNoErrors();

        // 08:00 + 60 นาที (ไม่ใช่ 07:00 + 60 นาที)
        $this->assertSame('09:00', Booking::sole()->checkinDeadline()->format('H:i'));
    }

    public function test_future_booking_expires_from_slot_start(): void
    {
        $date = Carbon::parse($this->bookableDate(Carbon::today()->addDays(3)));
        $this->travelTo(Carbon::today()->setTime(9, 0));

        [$employee, $desk] = $this->context();

        $this->actingAs($employee, 'web')
            ->from('/dashboard')
            ->post('/bookings', [
                'desk_id' => $desk->desk_id,
                'booking_date' => $date->toDateString(),
                'time_slot' => 'Full Day',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('09:00', Booking::sole()->checkinDeadline()->format('H:i'));
    }

    private function bookAfternoon(Employee $employee, Desk $desk, Carbon $date): void
    {
        $this->actingAs($employee, 'web')
            ->from('/dashboard')
            ->post('/bookings', [
                'desk_id' => $desk->desk_id,
                'booking_date' => $date->toDateString(),
                'time_slot' => 'Afternoon',
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * วันที่ใช้ทดสอบ Same-Day Walk-in (วันธรรมดาที่ยังไม่เกินวันหยุด)
     */
    private function walkInDate(): Carbon
    {
        return Carbon::parse($this->bookableDate());
    }

    /**
     * @return array{0: Employee, 1: Desk}
     */
    private function context(): array
    {
        $zone = Zone::factory()->create();
        $desk = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $employee = Employee::factory()->create();

        return [$employee, $desk];
    }
}
