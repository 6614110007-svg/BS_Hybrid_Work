<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Desk;
use App\Models\Employee;
use App\Support\TimeSlot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $slot = fake()->randomElement(TimeSlot::all());

        return [
            'employee_id' => Employee::factory(),
            'desk_id' => Desk::factory(),
            'booking_date' => Carbon::today()->toDateString(),
            'time_slot' => $slot->name,
            'start_time' => $slot->start,
            'end_time' => $slot->end,
            'actual_checkin_time' => null,
            'actual_checkout_time' => null,
            'checkin_photo' => null,
            'booking_status' => Booking::STATUS_RESERVED,
        ];
    }

    public function forSlot(string $slotName): static
    {
        $slot = TimeSlot::find($slotName);

        return $this->state(fn () => [
            'time_slot' => $slot->name,
            'start_time' => $slot->start,
            'end_time' => $slot->end,
        ]);
    }

    public function on(string $date): static
    {
        return $this->state(fn () => ['booking_date' => $date]);
    }

    public function reserved(): static
    {
        return $this->state(fn () => ['booking_status' => Booking::STATUS_RESERVED]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => [
            'booking_status' => Booking::STATUS_CHECKED_IN,
            'actual_checkin_time' => Carbon::now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'booking_status' => Booking::STATUS_COMPLETED,
            'actual_checkin_time' => Carbon::now()->subHours(3),
            'actual_checkout_time' => Carbon::now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['booking_status' => Booking::STATUS_EXPIRED]);
    }

    public function withPhoto(?string $path = 'checkins/demo/photo.jpg'): static
    {
        return $this->state(fn () => ['checkin_photo' => $path]);
    }
}
