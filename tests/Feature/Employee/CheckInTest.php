<?php

namespace Tests\Feature\Employee;

use App\Models\Booking;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Support\TimeSlot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkin_page_is_available_inside_the_window(): void
    {
        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $this->actingAs($employee, 'web')
            ->get('/bookings/'.$booking->booking_id.'/checkin')
            ->assertOk()
            ->assertSee('ถ่ายรูปเซลฟี่');
    }

    public function test_checkin_is_rejected_before_the_window_opens(): void
    {
        [$employee, $booking] = $this->reservation(Carbon::tomorrow());

        $this->travelTo(now());

        $this->actingAs($employee, 'web')
            ->get('/bookings/'.$booking->booking_id.'/checkin')
            ->assertRedirect('/bookings')
            ->assertSessionHas('error');
    }

    public function test_employee_can_checkin_with_a_photo(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response(['Key' => 'checkins/x.jpg'], 200)]);

        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $response = $this->actingAs($employee, 'web')
            ->post('/bookings/'.$booking->booking_id.'/checkin', [
                'photo' => UploadedFile::fake()->image('selfie.jpg'),
            ]);

        $response->assertRedirect('/bookings')->assertSessionHas('success');

        $booking->refresh();

        $this->assertSame(Booking::STATUS_CHECKED_IN, $booking->booking_status);
        $this->assertNotNull($booking->actual_checkin_time);
        $this->assertNotNull($booking->checkin_photo);
        $this->assertNull($booking->actual_checkout_time);
        $this->assertSame(Desk::STATUS_CHECKED_IN, $booking->desk->fresh()->desk_status);
    }

    public function test_checkin_requires_a_photo(): void
    {
        Http::fake();

        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $this->actingAs($employee, 'web')
            ->post('/bookings/'.$booking->booking_id.'/checkin', [])
            ->assertSessionHasErrors('photo');

        $this->assertSame(Booking::STATUS_RESERVED, $booking->fresh()->booking_status);
    }

    public function test_late_checkin_cancels_the_booking(): void
    {
        Http::fake();

        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinDeadline()->addMinute());

        $this->actingAs($employee, 'web')
            ->post('/bookings/'.$booking->booking_id.'/checkin', [
                'photo' => UploadedFile::fake()->image('selfie.jpg'),
            ])
            ->assertRedirect('/bookings')
            ->assertSessionHas('error');

        $this->assertSame(Booking::STATUS_EXPIRED, $booking->fresh()->booking_status);
        $this->assertSame(Desk::STATUS_AVAILABLE, $booking->desk->fresh()->desk_status);
    }

    public function test_employee_cannot_checkin_someone_elses_booking(): void
    {
        [, $booking] = $this->reservation(now());
        $intruder = Employee::factory()->create();

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $this->actingAs($intruder, 'web')
            ->get('/bookings/'.$booking->booking_id.'/checkin')
            ->assertForbidden();
    }

    public function test_employee_can_checkout(): void
    {
        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $booking->forceFill([
            'booking_status' => Booking::STATUS_CHECKED_IN,
            'actual_checkin_time' => now(),
        ])->save();

        $this->actingAs($employee, 'web')
            ->post('/bookings/'.$booking->booking_id.'/checkout')
            ->assertRedirect('/bookings')
            ->assertSessionHas('success');

        $booking->refresh();

        $this->assertSame(Booking::STATUS_COMPLETED, $booking->booking_status);
        $this->assertNotNull($booking->actual_checkout_time);
    }

    public function test_checkout_is_rejected_for_reserved_booking(): void
    {
        [$employee, $booking] = $this->reservation(now());

        $this->travelTo($booking->checkinOpensAt()->addMinute());

        $this->actingAs($employee, 'web')
            ->post('/bookings/'.$booking->booking_id.'/checkout')
            ->assertStatus(422);

        $this->assertSame(Booking::STATUS_RESERVED, $booking->fresh()->booking_status);
    }

    /**
     * สร้างใบจองสถานะ 'จองแล้ว (รอเช็คอิน)' โดยยังไม่เลื่อนเวลา
     *
     * @return array{0: Employee, 1: Booking}
     */
    private function reservation(Carbon $date): array
    {
        $zone = Zone::factory()->create();
        $desk = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $employee = Employee::factory()->create();
        $slot = TimeSlot::find('Full Day');

        $booking = Booking::factory()->create([
            'employee_id' => $employee->employee_id,
            'desk_id' => $desk->desk_id,
            'booking_date' => $date->toDateString(),
            'time_slot' => $slot->name,
            'start_time' => $slot->start,
            'end_time' => $slot->end,
            'booking_status' => Booking::STATUS_RESERVED,
        ]);

        return [$employee, $booking];
    }
}
