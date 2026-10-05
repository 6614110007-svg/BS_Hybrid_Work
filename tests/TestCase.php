<?php

namespace Tests;

use App\Support\Holiday;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * วันที่ที่จองได้จริง (วันธรรมดา ไม่ใช่วันหยุดนักขัตฤกษ์) ใช้กับเทสต์ที่ยิง
     * POST /bookings เพราะปฏิทินปิดเสาร์-อาทิตย์และวันหยุดไว้แล้ว
     */
    protected function bookableDate(Carbon|string|null $date = null): string
    {
        $candidate = $date ? Carbon::parse($date) : Carbon::today();
        $maxDate = Carbon::today()->addDays((int) config('booking.lead_days', 14));

        while (! Holiday::isBookable($candidate) && $candidate->lte($maxDate)) {
            $candidate->addDay();
        }

        return $candidate->toDateString();
    }

    /**
     * ตรึง "วันนี้" ไว้ที่วันธรรมดาที่จองได้ แล้วเลื่อนออกไปถ้าวันนั้นตรงวันหยุด
     *
     * เทสต์ที่พึ่งพา desk_status ของวันนี้ (เช่นการจองแล้วโต๊ะต้องเป็น Reserved)
     * จะผันผวนตามปฏิทินจริง ถ้าวันที่รันเทสต์ตรงกับเสาร์-อาทิตย์หรือวันหยุดนักขัตฤกษ์
     * เพราะ bookableDate() จะเลื่อนไปวันจองได้วันถัดไป แต่ desk_status ยังคำนวณจากวันนี้
     * ฟังก์ชันนี้ตรึงเวลาไว้ที่วันพุธที่จองได้ จึงรันได้ผลลัพธ์เดียวกันทุกวัน
     */
    protected function freezeToBookableToday(): Carbon
    {
        $candidate = Carbon::create(2026, 1, 7, 9, 0, 0); // วันพุธ

        while (! Holiday::isBookable($candidate)) {
            $candidate->addDay();
        }

        Carbon::setTestNow($candidate);

        $this->beforeApplicationDestroyed(fn () => Carbon::setTestNow());

        return $candidate;
    }

    /**
     * วันที่จองได้วันสุดท้ายของช่วงล่วงหน้า (เดินถอยจากวันสุดท้ายที่เปิดไว้)
     */
    protected function lastBookableDate(): string
    {
        $candidate = Carbon::today()->addDays((int) config('booking.lead_days', 14));

        while (! Holiday::isBookable($candidate)) {
            $candidate->subDay();
        }

        return $candidate->toDateString();
    }
}
