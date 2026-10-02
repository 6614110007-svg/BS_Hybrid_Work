<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * วันที่ห้ามจองโต๊ะ = เสาร์-อาทิตย์ + วันหยุดนักขัตฤกษ์
 *
 * รายการวันหยุดกำหนดไว้ที่ config/booking.php คีย์ 'holidays' (รูปแบบ YYYY-MM-DD)
 * ใช้ร่วมกันทั้งฝั่ง Backend และ Frontend เพื่อไม่ให้สองฝั่งนิยามไม่ตรงกัน
 */
final class Holiday
{
    public const WEEKEND_REASON = 'วันหยุดเสาร์-อาทิตย์';

    public const HOLIDAY_REASON = 'วันหยุดนักขัตฤกษ์';

    /**
     * cache รายการวันหยุด เพราะอ่านซ้ำทุกครั้งที่เปิดปฏิทินหรือตรวจใบจอง
     * รายการนี้เปลี่ยนปีละครั้ง จึง cache ได้นานโดยไม่กระทบความถูกต้อง
     */
    private const CACHE_KEY = 'booking:holidays';

    private const CACHE_TTL_MINUTES = 720;

    /**
     * วันหยุดทั้งหมด รูปแบบ ['YYYY-MM-DD' => 'ชื่อวันหยุด']
     *
     * @return array<string, string>
     */
    public static function names(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn (): array => (array) config('booking.holidays', []),
        );
    }

    /**
     * ล้าง cache เมื่อแก้รายการวันหยุดใน config/booking.php
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, string>
     */
    public static function dates(): array
    {
        return array_keys(self::names());
    }

    public static function isWeekend(Carbon $date): bool
    {
        return $date->isSaturday() || $date->isSunday();
    }

    public static function isHoliday(Carbon|string $date): bool
    {
        return array_key_exists(self::key($date), self::names());
    }

    public static function name(Carbon|string $date): ?string
    {
        return self::names()[self::key($date)] ?? null;
    }

    /**
     * เหตุผลที่เลือกวันนั้นไม่ได้ — null = จองได้
     */
    public static function reason(Carbon|string $date): ?string
    {
        $date = self::parse($date);

        if (self::isWeekend($date)) {
            return self::WEEKEND_REASON;
        }

        if (self::isHoliday($date)) {
            return self::HOLIDAY_REASON;
        }

        return null;
    }

    public static function isBookable(Carbon|string $date): bool
    {
        return self::reason($date) === null;
    }

    private static function key(Carbon|string $date): string
    {
        return $date instanceof Carbon ? $date->toDateString() : $date;
    }

    private static function parse(Carbon|string $date): Carbon
    {
        return $date instanceof Carbon ? $date : Carbon::parse($date);
    }
}
