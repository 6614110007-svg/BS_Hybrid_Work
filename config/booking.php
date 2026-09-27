<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Time Slots
    |--------------------------------------------------------------------------
    | ช่วงเวลาที่เปิดให้จองโต๊ะ เก็บเป็น VARCHAR บน booking.time_slot
    | ตาม Database Schema ไม่มีตาราง time_slots จึงกำหนดไว้ที่ config
    | เพิ่มหรือลดช่วงเวลาได้ที่นี่ที่เดียว
    */

    'slots' => [
        'Full Day' => ['start' => '08:00', 'end' => '18:00'],
        'Morning' => ['start' => '08:00', 'end' => '12:00'],
        'Afternoon' => ['start' => '13:00', 'end' => '18:00'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Booking Policy
    |--------------------------------------------------------------------------
    */

    // ให้จองล่วงหน้าได้กี่วันจากวันนี้ (รวมวันนี้)
    'lead_days' => (int) env('BOOKING_LEAD_DAYS', 14),

    // เช็คอินได้ก่อนเวลาเริ่มกี่นาที
    'early_checkin_minutes' => (int) env('BOOKING_EARLY_MINUTES', 60),

    // ไม่กดเช็คอินภายในกี่นาทีนับจากเวลาเริ่ม ระบบจะเปลี่ยนเป็น 'X' (Expired)
    'late_grace_minutes' => (int) env('BOOKING_LATE_GRACE_MINUTES', 60),

    // เช็คอินแล้วค้างเกินเวลาสิ้นสุดกี่นาที ระบบจะปิดใบจองให้อัตโนมัติ
    'auto_checkout_minutes' => (int) env('BOOKING_AUTO_CHECKOUT_MINUTES', 60),

    // ชนิดไฟล์รูปเซลฟี่ที่อนุญาต
    'checkin_photo_mimes' => ['jpeg', 'jpg', 'png', 'webp'],

    // Limit ขนาดรูป Selfie (KB)
    'checkin_photo_max_kb' => (int) env('CHECKIN_PHOTO_MAX_KB', 5120),

];
