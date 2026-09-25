<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Desk;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $slot = TimeSlot::query()->inRandomOrder()->first()
            ?? TimeSlot::create([
                'code' => 'AM',
                'name' => 'ช่วงเช้า 09:00-13:00',
                'start_time' => '09:00',
                'end_time' => '13:00',
                'is_active' => true,
                'sort_order' => 1,
            ]);

        $date = now()->addDays(1)->toDateString();
        $startsAt = \Illuminate\Support\Carbon::parse($date.' '.$slot->start_time);
        $endsAt = \Illuminate\Support\Carbon::parse($date.' '.$slot->end_time);

        return [
            'user_id' => User::factory(),
            'desk_id' => Desk::factory(),
            'time_slot_id' => $slot->id,
            'booking_date' => $date,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => Booking::STATUS_CONFIRMED,
        ];
    }
}