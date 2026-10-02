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
