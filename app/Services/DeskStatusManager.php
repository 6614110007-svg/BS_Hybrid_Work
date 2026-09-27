<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Desk;
use Carbon\Carbon;

/**
 * คำนวณ desk_status (Available / Reserved / Checked-In) ของโต๊ะจากสถานะการจองของวันนี้
 *
 * โต๊ะที่ถูกปิดซ่อมบำรุง (Maintenance) เป็นสถานะที่ผู้ดูแลระบบควบคุมเอง
 * ระบบจะไม่แตะต้องสถานะนั้น
 */
class DeskStatusManager
{
    public function sync(Desk $desk): Desk
    {
        if ($desk->isMaintenance()) {
            return $desk;
        }

        $status = $this->resolveStatus($desk);

        if ($desk->desk_status !== $status) {
            $desk->forceFill(['desk_status' => $status])->save();
        }

        return $desk;
    }

    private function resolveStatus(Desk $desk): string
    {
        $today = Carbon::today()->toDateString();

        $bookingsToday = Booking::forDate($today)->where('desk_id', $desk->desk_id);

        if ((clone $bookingsToday)->where('booking_status', Booking::STATUS_CHECKED_IN)->exists()) {
            return Desk::STATUS_CHECKED_IN;
        }

        if ((clone $bookingsToday)->where('booking_status', Booking::STATUS_RESERVED)->exists()) {
            return Desk::STATUS_RESERVED;
        }

        return Desk::STATUS_AVAILABLE;
    }
}
