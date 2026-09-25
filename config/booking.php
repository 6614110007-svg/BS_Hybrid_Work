<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Booking policy
    |--------------------------------------------------------------------------
    */

    // ให้จองล่วงหน้าได้กี่วันจากวันนี้ (รวมวันนี้)
    'lead_days' => (int) env('BOOKING_LEAD_DAYS', 14),

    // เช็คอินได้ก่อนเวลาเริ่มกี่นาที
    'early_checkin_minutes' => (int) env('BOOKING_EARLY_MINUTES', 60),

    // เช็คอินสายเกินเวลากี่นาทีถือว่าเลยกำหนด (auto-cancel)
    'late_grace_minutes' => (int) env('BOOKING_LATE_GRACE_MINUTES', 60),

    // ยังไม่เช็คเอาต์เกินเวลาสิ้นสุดกี่นาทีให้ auto-checkout
    'auto_checkout_minutes' => (int) env('BOOKING_AUTO_CHECKOUT_MINUTES', 60),

    // Limit ขนาดรูป Selfie (KB)
    'checkin_photo_max_kb' => (int) env('CHECKIN_PHOTO_MAX_KB', 5120),

];