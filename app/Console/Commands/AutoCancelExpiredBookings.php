<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoCancelExpiredBookings extends Command
{
    protected $signature = 'bookings:auto-cancel';

    protected $description = 'ยกเลิกใบจองที่เลยเวลาเช็คอินเกินกำหนด (ไม่มี actual_checkin_time) และคืนสถานะโต๊ะ';

    public function handle(): int
    {
        $now = Carbon::now();
        $grace = (int) config('booking.late_grace_minutes', 60);
        $today = $now->toDateString();
        $cutoff = $now->copy()->subMinutes($grace)->format('H:i:s');

        $cancelled = 0;

        Booking::query()
            ->where('booking_status', Booking::STATUS_RESERVED)
            ->whereNull('actual_checkin_time')
            ->where(function ($query) use ($today, $cutoff) {
                $query->whereDate('booking_date', '<', $today)
                    ->orWhere(function ($inner) use ($today, $cutoff) {
                        $inner->whereDate('booking_date', $today)
                            ->whereRaw('start_time <= ?', [$cutoff]);
                    });
            })
            ->with('desk')
            ->chunkById(200, function ($bookings) use (&$cancelled) {
                foreach ($bookings as $booking) {
                    $booking->forceFill(['booking_status' => Booking::STATUS_EXPIRED])->save();

                    $cancelled++;
                }
            });

        $this->info('ยกเลิกใบจองที่เลยกำหนด '.$cancelled.' รายการ');

        return self::SUCCESS;
    }
}
