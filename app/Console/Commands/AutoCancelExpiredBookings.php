<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Notifications\BookingAutoCancelled;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoCancelExpiredBookings extends Command
{
    protected $signature = 'bookings:auto-cancel';

    protected $description = 'ยกเลิกใบจองที่เลยเวลาเช็คอินเกินกำหนด และปิดการใช้งานโต๊ะค้างค้าง';

    public function handle(): int
    {
        $now = Carbon::now();
        $grace = (int) config('booking.late_grace_minutes', 60);
        $autoOut = (int) config('booking.auto_checkout_minutes', 60);

        // 1) confirmed แต่เลยเวลาเช็คอิน (starts_at + grace)
        $lateConfirmed = Booking::where('status', Booking::STATUS_CONFIRMED)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<', $now->copy()->subMinutes($grace))
            ->get();

        // 2) checked_in แต่ค้างจนเกินเวลาสิ้นสุด (ends_at + autoOut) -> auto check-out
        $staleCheckedIn = Booking::where('status', Booking::STATUS_CHECKED_IN)
            ->where('ends_at', '<', $now->copy()->subMinutes($autoOut))
            ->get();

        DB::transaction(function () use ($lateConfirmed, $staleCheckedIn) {
            foreach ($lateConfirmed as $booking) {
                $booking->update([
                    'status' => Booking::STATUS_EXPIRED,
                    'cancel_reason' => Booking::CANCEL_AUTO_LATE,
                ]);
                $booking->user->notify(new BookingAutoCancelled($booking));
            }

            foreach ($staleCheckedIn as $booking) {
                $booking->update([
                    'status' => Booking::STATUS_CHECKED_OUT,
                    'checked_out_at' => $booking->ends_at,
                ]);
            }
        });

        $this->info("ยกเลิกใบจองที่เลยกำหนด {$lateConfirmed->count()} รายการ, auto-checkout {$staleCheckedIn->count()} รายการ");

        return self::SUCCESS;
    }
}