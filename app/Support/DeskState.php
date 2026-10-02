<?php

namespace App\Support;

/**
 * สถานะโต๊ะบนผังที่นั่ง — ใช้ร่วมกันระหว่าง Blade และ JavaScript (ผ่าน SEATMAP_META)
 *
 * ย้ายออกจากหน้า seatmap/index.blade.php เพื่อให้ส่วนที่โหลดผ่าน AJAX
 * ใช้ป้ายกำกับชุดเดียวกับหน้าเต็ม ไม่ต้องเขียนซ้ำ
 */
final class DeskState
{
    public const AVAILABLE = 'available';

    public const BOOKED = 'booked';

    public const IN_USE = 'in_use';

    public const MY_BOOKED = 'my_booked';

    public const MY_IN_USE = 'my_in_use';

    public const MAINTENANCE = 'maintenance';

    /**
     * ป้ายกำกับ + สีประจำสถานะ
     *
     * @return array<string, array{label: string, dot: string, card: string}>
     */
    public static function meta(): array
    {
        return [
            self::AVAILABLE => ['label' => 'ว่าง', 'dot' => 'bg-emerald-400', 'card' => 'border-emerald-200 bg-emerald-50'],
            self::BOOKED => ['label' => 'ถูกจอง', 'dot' => 'bg-amber-400', 'card' => 'border-amber-200 bg-amber-50'],
            self::IN_USE => ['label' => 'กำลังใช้งาน', 'dot' => 'bg-sky-400', 'card' => 'border-sky-200 bg-sky-50'],
            self::MY_BOOKED => ['label' => 'คุณจองแล้ว', 'dot' => 'bg-indigo-500', 'card' => 'border-indigo-300 bg-indigo-50'],
            self::MY_IN_USE => ['label' => 'คุณกำลังใช้งาน', 'dot' => 'bg-indigo-600', 'card' => 'border-indigo-400 bg-indigo-100'],
            self::MAINTENANCE => ['label' => 'ปิดซ่อมบำรุง', 'dot' => 'bg-rose-400', 'card' => 'border-rose-200 bg-rose-50'],
        ];
    }

    public static function label(string $state): string
    {
        return self::meta()[$state]['label'] ?? self::meta()[self::AVAILABLE]['label'];
    }

    /**
     * สถานะของโต๊ะหนึ่งตัว
     *
     * @param  ?object  $booking  Booking ที่ยังใช้งานอยู่ของโต๊ะนี้ หรือ null
     */
    public static function resolve(object $desk, ?object $booking, string $employeeId): string
    {
        if ($desk->isMaintenance()) {
            return self::MAINTENANCE;
        }

        if ($booking === null) {
            return self::AVAILABLE;
        }

        $mine = $booking->employee_id === $employeeId;
        $inUse = $booking->isCheckedIn();

        return match (true) {
            $inUse && $mine => self::MY_IN_USE,
            $inUse => self::IN_USE,
            $mine => self::MY_BOOKED,
            default => self::BOOKED,
        };
    }
}
