<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ตรวจสอบใบจองที่เลยเวลาเช็คอินทุกๆ 5 นาที (ยกเลิกอัตโนมัติ + แจ้งเตือน)
Schedule::command('bookings:auto-cancel')->everyFiveMinutes();
