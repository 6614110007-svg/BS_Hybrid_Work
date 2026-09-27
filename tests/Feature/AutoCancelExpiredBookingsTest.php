<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Support\TimeSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCancelExpiredBookingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_without_checkin_is_expired_after_the_grace_period(): void
    {
        $booking = $this->todayReservation();

        $this->travelTo($booking->checkinDeadline()->addMinute());

        $this->artisan('bookings:auto-cancel')
            ->expectsOutputToContain('ยกเลิกใบจองที่เลยกำหนด 1 รายการ')
            ->assertSuccessful();

        $this->assertSame(Booking::STATUS_EXPIRED, $booking->fresh()->booking_status);
        $this->assertSame(Desk::STATUS_AVAILABLE, $booking->desk->fresh()->desk_status);
    }

    public function test_reservation_inside_the_grace_period_is_kept(): void
    {
        $booking = $this->todayReservation();

        $this->travelTo($booking->startsAt());

        $this->artisan('bookings:auto-cancel')->assertSuccessful();

        $this->assertSame(Booking::STATUS_RESERVED, $booking->fresh()->booking_status);
        $this->assertSame(Desk::STATUS_RESERVED, $booking->desk->fresh()->desk_status);
    }

    public function test_checked_in_booking_is_never_auto_cancelled(): void
    {
        $booking = $this->todayReservation();

        $booking->forceFill([
            'booking_status' => Booking::STATUS_CHECKED_IN,
            'actual_checkin_time' => now(),
        ])->save();

        $this->travelTo($booking->checkinDeadline()->addHours(3));

        $this->artisan('bookings:auto-cancel')->assertSuccessful();

        $this->assertSame(Booking::STATUS_CHECKED_IN, $booking->fresh()->booking_status);
    }

    public function test_past_reservation_is_expired_immediately(): void
    {
        $booking = $this->todayReservation();

        $this->travelTo(now()->addDay());

        $this->artisan('bookings:auto-cancel')->assertSuccessful();

        $this->assertSame(Booking::STATUS_EXPIRED, $booking->fresh()->booking_status);
    }

    public function test_command_is_scheduled_every_five_minutes(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'bookings:auto-cancel'));

        $this->assertCount(1, $events);
        $this->assertSame('*/5 * * * *', $events->first()->expression);
    }

    private function todayReservation(): Booking
    {
        $zone = Zone::factory()->create();
        $desk = Desk::factory()->create(['zone_id' => $zone->zone_id]);
        $employee = Employee::factory()->create();
        $slot = TimeSlot::find('Full Day');

        return Booking::factory()->create([
            'employee_id' => $employee->employee_id,
            'desk_id' => $desk->desk_id,
            'booking_date' => now()->toDateString(),
            'time_slot' => $slot->name,
            'start_time' => $slot->start,
            'end_time' => $slot->end,
            'booking_status' => Booking::STATUS_RESERVED,
        ]);
    }
}
