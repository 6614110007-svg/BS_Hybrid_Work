<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoCancelExpiredBookings extends Command
{
    protected $signature = 'bookings:auto-cancel';

    protected $description = 'ปิดวงจองของเมื่อวาน: ยกเลิกใบจองที่เลยเวลาเช็คอินเกินกำหนด (ไม่มี actual_checkin_time) และตัดบิลที่ค้างสถานะ Checked-In พร้อมคืนสถานะโต๊ะ';

    public function handle(): int
    {
        $now = Carbon::now();
        $today = $now->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        $cancelled = 0;

        Booking::query()
            ->where('booking_status', Booking::STATUS_RESERVED)
            ->whereNull('actual_checkin_time')
            ->whereDate('booking_date', '<=', $today)
            ->with('desk')
            ->chunkById(200, function ($bookings) use (&$cancelled, $now, $today) {
                foreach ($bookings as $booking) {
                    // ใบจองของวันก่อนหน้า -> หมดอายุทันที
                    // ส่วนวันนี้ใช้ Booking::checkinDeadline() ซึ่งรองรับ Same-Day Walk-in
                    // (นับ 60 นาทีจากเวลาที่กดจอง ไม่ใช่จากเวลาเริ่มสล็อต)
                    $isExpired = $booking->booking_date->toDateString() < $today
                        || $now->gt($booking->checkinDeadline());

                    if (! $isExpired) {
                        continue;
                    }

                    $booking->forceFill(['booking_status' => Booking::STATUS_EXPIRED])->save();

                    $cancelled++;
                }
            });

        $closed = $this->closeYesterdayCheckins($yesterday);

        $this->info('ยกเลิกใบจองที่เลยกำหนด '.$cancelled.' รายการ');
        $this->info('ตัดบิลที่ค้างสถานะเช็คอินของเมื่อวาน '.$closed.' รายการ');

        return self::SUCCESS;
    }

    /**
     * ตัดบิลของ "เมื่อวาน" ที่ยังค้างสถานะ Checked-In ให้เป็น Completed
     *
     * ผู้ใช้ที่กดเช็คอินแล้วไม่กดเช็คเอาต์จนข้ามวัน จะไม่มีการ save ใบจองอีก
     * ทำให้ Booking::booted() ไม่เคย sync และโต๊ะค้างสถานะ Checked-In ข้ามวัน
     * การตัดบิลตรงนี้คือ end-of-day boundary ที่ปลดล็อกโต๊ะให้พร้อมใช้งานในวันถัดไป
     *
     * จำกัดขอบเขตเฉพาะเมื่อวาน เพื่อไม่ไปแตะข้อมูลย้อนหลัง
     */
    private function closeYesterdayCheckins(string $yesterday): int
    {
        $closed = 0;

        Booking::query()
            ->forDate($yesterday)
            ->where('booking_status', Booking::STATUS_CHECKED_IN)
            ->whereNull('actual_checkout_time')
            ->with('desk')
            ->chunkById(200, function ($bookings) use (&$closed) {
                foreach ($bookings as $booking) {
                    $booking->forceFill([
                        'booking_status' => Booking::STATUS_COMPLETED,
                        // ตัดบิลที่สิ้นสุดของวันที่จอง ไม่ใช่เวลาที่ scheduler ทำงาน
                        'actual_checkout_time' => $booking->booking_date->copy()->endOfDay(),
                    ])->save();

                    $closed++;
                }
            });

        return $closed;
    }
}
