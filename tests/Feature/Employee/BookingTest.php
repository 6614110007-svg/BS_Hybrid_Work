<?php

namespace Tests\Feature\Employee;

use App\Http\Controllers\BookingController;
use App\Models\Booking;
use App\Models\Desk;
use App\Models\Employee;
use App\Models\Zone;
use App\Support\SelfieChallenge;
use App\Support\TimeSlot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_book_an_available_desk(): void
    {
        [$employee, $desk] = $this->bookingContext();
        $slot = TimeSlot::find('Full Day');
        $date = $this->bookableDate();

        $this->actingAs($employee, 'web')
            ->from('/dashboard')
            ->post('/bookings', [
                'desk_id' => $desk->desk_id,
                'booking_date' => $date,
                'time_slot' => $slot->name,
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('success');

        $booking = Booking::where('desk_id', $desk->desk_id)->sole();

        $this->assertSame($employee->employee_id, $booking->employee_id);
        $this->assertSame(Booking::STATUS_RESERVED, $booking->booking_status);
        $this->assertSame($slot->start, $booking->start_time->format('H:i'));
        $this->assertSame($slot->end, $booking->end_time->format('H:i'));
        $this->assertMatchesRegularExpression('/^BKG\d{8}$/', $booking->booking_id);
    }

    public function test_desk_is_marked_reserved_for_today(): void
    {
        [$employee, $desk] = $this->bookingContext();
        $slot = TimeSlot::find('Full Day');

        $this->actingAs($employee, 'web')->post('/bookings', [
            'desk_id' => $desk->desk_id,
            'booking_date' => $this->bookableDate(),
            'time_slot' => $slot->name,
        ]);

        $this->assertSame(Desk::STATUS_RESERVED, $desk->fresh()->desk_status);
    }

    public function test_desk_cannot_be_booked_twice_in_the_same_slot(): void
    {
        [$employee, $desk] = $this->bookingContext();
        $slot = TimeSlot::find('Full Day');
        $date = $this->bookableDate();

        $other = Employee::factory()->create();

        Booking::factory()->create([
            'employee_id' => $other->employee_id,
            'desk_id' => $desk->desk_id,
            'booking_date' => $date,
            'time_slot' => $slot->name,
        ]);

        $this->actingAs($employee, 'web')
            ->from('/dashboard')
            ->post('/bookings', [
                'desk_id' => $desk->desk_id,
                'booking_date' => $date,
                'time_slot' => $slot->name,
            ])
            ->assertSessionHasErrors('desk_id');

        $this->assertSame(1, Booking::where('desk_id', $desk->desk_id)->count());
    }

    public function test_employee_cannot_hold_two_desks_in_the_same_slot(): void
    {
        [$employee, $desk] = $this->bookingContext();
        $slot = TimeSlot::find('Full Day');
        $date = $this->bookableDate();
        $second = Desk::factory()->create(['zone_id' => $desk->zone_id]);

        Booking::factory()->create([
            'employee_id' => $employee->employee_id,
            'desk_id' => $desk->desk_id,
            'booking_date' => $date,
            'time_slot' => $slot->name,
        ]);

        $this->actingAs($employee, 'web')
            ->from('/dashboard')
            ->post('/bookings', [
                'desk_id' => $second->desk_id,
                'booking_date' => $date,
                'time_slot' => $slot->name,
            ])
            ->assertSessionHasErrors('desk_id');
    }

    public function test_employee_can_book_two_different_slots_on_the_same_day(): void
    {
        [$employee, $desk] = $this->bookingContext();
        $date = $this->bookableDate();
        $second = Desk::factory()->create(['zone_id' => $desk->zone_id]);

        $this->actingAs($employee, 'web')->post('/bookings', [
            'desk_id' => $desk->desk_id,
            'booking_date' => $date,
            'time_slot' => 'Morning',
        ])->assertSessionHasNoErrors();

        $this->actingAs($employee, 'web')->post('/bookings', [
            'desk_id' => $second->desk_id,
            'booking_date' => $date,
            'time_slot' => 'Afternoon',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Booking::where('employee_id', $employee->employee_id)->count());
    }

    public function test_maintenance_desk_cannot_be_booked(): void
    {
        [$employee, $desk] = $this->bookingContext();
        $desk->forceFill(['desk_status' => Desk::STATUS_MAINTENANCE])->save();

        $this->actingAs($employee, 'web')
            ->from('/dashboard')
            ->post('/bookings', [
                'desk_id' => $desk->desk_id,
                'booking_date' => $this->bookableDate(),
                'time_slot' => 'Full Day',
            ])
            ->assertSessionHasErrors('desk_id');

        $this->assertSame(0, Booking::count());
    }

    public function test_booking_date_must_be_within_lead_window(): void
    {
        [$employee, $desk] = $this->bookingContext();

        $this->actingAs($employee, 'web')
            ->from('/dashboard')
            ->post('/bookings', [
                'desk_id' => $desk->desk_id,
                'booking_date' => now()->subDay()->toDateString(),
                'time_slot' => 'Full Day',
            ])
            ->assertSessionHasErrors('booking_date');
    }

    public function test_unknown_time_slot_is_rejected(): void
    {
        [$employee, $desk] = $this->bookingContext();

        $this->actingAs($employee, 'web')
            ->from('/dashboard')
            ->post('/bookings', [
                'desk_id' => $desk->desk_id,
                'booking_date' => $this->bookableDate(),
                'time_slot' => 'Night',
            ])
            ->assertSessionHasErrors('time_slot');
    }

    public function test_booking_on_saturday_is_rejected(): void
    {
        [$employee, $desk] = $this->bookingContext();

        $saturday = Carbon::today()->next(Carbon::SATURDAY);

        $this->actingAs($employee, 'web')
            ->from('/dashboard')
            ->post('/bookings', [
                'desk_id' => $desk->desk_id,
                'booking_date' => $saturday->toDateString(),
                'time_slot' => 'Full Day',
            ])
            ->assertSessionHasErrors('booking_date')
            ->assertSessionHasErrors([
                'booking_date' => BookingController::NON_BOOKABLE_DATE_MESSAGE,
            ]);

        $this->assertSame(0, Booking::count());
    }

    public function test_booking_on_sunday_is_rejected(): void
    {
        [$employee, $desk] = $this->bookingContext();

        $sunday = Carbon::today()->next(Carbon::SUNDAY);

        $this->actingAs($employee, 'web')
            ->from('/dashboard')
            ->post('/bookings', [
                'desk_id' => $desk->desk_id,
                'booking_date' => $sunday->toDateString(),
                'time_slot' => 'Full Day',
            ])
            ->assertSessionHasErrors([
                'booking_date' => BookingController::NON_BOOKABLE_DATE_MESSAGE,
            ]);
    }

    #[DataProvider('holidayProvider')]
    public function test_booking_on_public_holiday_is_rejected(string $holiday): void
    {
        [$employee, $desk] = $this->bookingContext();

        $this->actingAs($employee, 'web')
            ->from('/dashboard')
            ->post('/bookings', [
                'desk_id' => $desk->desk_id,
                'booking_date' => $holiday,
                'time_slot' => 'Full Day',
            ])
            ->assertSessionHasErrors([
                'booking_date' => BookingController::NON_BOOKABLE_DATE_MESSAGE,
            ]);

        $this->assertSame(0, Booking::count());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function holidayProvider(): array
    {
        return [
            'วันขึ้นปีใหม่' => ['2026-01-01'],
            'วันจักรี' => ['2026-04-06'],
            'วันสงกรานต์' => ['2026-04-13'],
            'วันหยุดชดเชยวันวิสาขบูชา' => ['2026-06-01'],
            'วันอาสาฬหบูชา' => ['2026-07-29'],
            'วันหยุดชดเชยวันพ่อแห่งชาติ' => ['2026-12-07'],
        ];
    }

    public function test_last_bookable_day_of_the_lead_window_is_allowed(): void
    {
        [$employee, $desk] = $this->bookingContext();
        $lastDay = $this->lastBookableDate();

        $this->actingAs($employee, 'web')
            ->from('/dashboard')
            ->post('/bookings', [
                'desk_id' => $desk->desk_id,
                'booking_date' => $lastDay,
                'time_slot' => 'Full Day',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Booking::count());
    }

    public function test_employee_can_cancel_own_reservation_and_desk_becomes_available(): void
    {
        [$employee, $desk] = $this->bookingContext();

        $booking = Booking::factory()->create([
            'employee_id' => $employee->employee_id,
            'desk_id' => $desk->desk_id,
            'booking_date' => $this->bookableDate(),
            'time_slot' => 'Full Day',
        ]);

        $this->actingAs($employee, 'web')
            ->from('/bookings')
            ->delete('/bookings/'.$booking->booking_id)
            ->assertRedirect('/bookings')
            ->assertSessionHas('success');

        $this->assertSame(Booking::STATUS_EXPIRED, $booking->fresh()->booking_status);
        $this->assertSame(Desk::STATUS_AVAILABLE, $desk->fresh()->desk_status);
    }

    public function test_employee_cannot_cancel_someone_elses_booking(): void
    {
        [, $desk] = $this->bookingContext();
        $owner = Employee::factory()->create();
        $intruder = Employee::factory()->create();

        $booking = Booking::factory()->create([
            'employee_id' => $owner->employee_id,
            'desk_id' => $desk->desk_id,
        ]);

        $this->actingAs($intruder, 'web')
            ->delete('/bookings/'.$booking->booking_id)
            ->assertForbidden();

        $this->assertSame(Booking::STATUS_RESERVED, $booking->fresh()->booking_status);
    }

    public function test_employee_can_list_own_bookings_only(): void
    {
        [$employee, $desk] = $this->bookingContext();
        $other = Employee::factory()->create();

        Booking::factory()->create([
            'employee_id' => $employee->employee_id,
            'desk_id' => $desk->desk_id,
        ]);

        Booking::factory()->create([
            'employee_id' => $other->employee_id,
            'desk_id' => Desk::factory()->create()->desk_id,
        ]);

        $this->actingAs($employee, 'web')
            ->get('/bookings')
            ->assertOk()
            ->assertViewHas('bookings', fn ($paginator) => $paginator->total() === 1);
    }

    public function test_checkin_photo_lightbox_exposes_details_for_the_booked_desk(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response(['Key' => 'checkins/x.jpg'], 200)]);

        [$employee, $desk] = $this->bookingContext();

        $prompt = SelfieChallenge::texts()[0];

        $booking = Booking::factory()->create([
            'employee_id' => $employee->employee_id,
            'desk_id' => $desk->desk_id,
            'booking_status' => Booking::STATUS_CHECKED_IN,
            'checkin_photo' => 'checkins/x.jpg',
            'actual_checkin_time' => now(),
            'selfie_prompt' => $prompt,
        ]);

        $this->actingAs($employee, 'web')
            ->get('/bookings')
            ->assertOk()
            ->assertSee('checkin-photo-data', false)
            ->assertSee('photoLightbox', false)
            ->assertSee($booking->booking_id, false)
            ->assertViewHas('photoViews', function (array $views) use ($booking, $employee, $prompt) {
                if (count($views) !== 1) {
                    return false;
                }

                return $views[0]['id'] === $booking->booking_id
                    && $views[0]['employee_name'] === $employee->employee_fullname
                    && $views[0]['department'] === $employee->department->department_name
                    && $views[0]['prompt'] === $prompt
                    && $views[0]['url'] !== null
                    && str_contains($views[0]['desk_label'], $booking->desk->desk_number);
            });
    }

    /**
     * @return array{0: Employee, 1: Desk}
     */
    private function bookingContext(): array
    {
        $zone = Zone::factory()->create();
        $desk = Desk::factory()->create(['zone_id' => $zone->zone_id]);

        $employee = Employee::factory()->create();

        return [$employee, $desk];
    }
}
