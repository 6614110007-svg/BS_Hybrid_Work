<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Zone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * cache ของรายการตัวเลือก (dropdown) ที่ถูกอ่านซ้ำบ่อยแต่เปลี่ยนไม่บ่อย
 *
 * ทุกจุดที่เพิ่ม/ลบ/แก้ผูกมาตรา แผนก โซน หรือโต๊ะ ต้องเรียก flush()
 * เพื่อไม่ให้ผู้ใช้เห็นรายการเก่าค้างอยู่ใน dropdown
 *
 * ข้อสำคัญ: เก็บเป็น array ของข้อมูลดิบ ไม่เก็บ Eloquent model/collection
 * เพราะ cache store แบบ file/database จะ serialize ค่านั้น แล้วตอนอ่านกลับ
 * จะกลายเป็น __PHP_Incomplete_Class ทันที (autoloader ไม่พร้อม ณ ตอน unserialize)
 * ทำให้ dropdown พังแบบ 500 Internal Server Error
 */
final class OptionCache
{
    private const DEPARTMENT_TTL_MINUTES = 10;

    private const ZONE_TTL_MINUTES = 10;

    /**
     * รายการแผนกสำหรับ dropdown
     *
     * @return Collection<int, object> องค์ประกอบมี department_id, department_name
     */
    public static function departments(): Collection
    {
        return self::rememberObjects(
            'options:departments',
            self::DEPARTMENT_TTL_MINUTES,
            ['department_id', 'department_name'],
            fn () => Department::orderBy('department_name')->get(),
        );
    }

    /**
     * รายการโซนสำหรับ dropdown
     *
     * @return Collection<int, object> องค์ประกอบมี zone_id, zone_name
     */
    public static function zones(): Collection
    {
        return self::rememberObjects(
            'options:zones',
            self::ZONE_TTL_MINUTES,
            ['zone_id', 'zone_name'],
            fn () => Zone::orderBy('zone_name')->get(),
        );
    }

    /**
     * ล้าง cache ทั้งหมด — เรียกหลังมีการเปลี่ยนแปลงข้อมูลหลัก
     */
    public static function flush(): void
    {
        Cache::forget('options:departments');
        Cache::forget('options:zones');
        Cache::forget('seatmap:zone-options');
        Cache::forget('analytics:zones-with-desks');
        // ยอดรวมบนหน้า admin dashboard นับจากแผนก/โซน/โต๊ะ/พนักงานเหมือนกัน
        Cache::forget('admin:dashboard:totals');
    }

    /**
     * cache เป็น array ของ array ข้อมูลดิบ แล้วคืนค่าเป็น Collection ของ stdClass
     *
     * @param  array<int, string>  $columns
     * @param  callable(): iterable<int, object>  $query
     * @return Collection<int, object>
     */
    private static function rememberObjects(string $key, int $minutes, array $columns, callable $query): Collection
    {
        $rows = Cache::remember(
            $key,
            now()->addMinutes($minutes),
            fn () => collect($query())
                ->map(fn ($model) => collect($columns)
                    ->mapWithKeys(fn (string $column) => [$column => $model->{$column}])
                    ->all())
                ->values()
                ->all(),
        );

        return collect($rows)->map(fn (array|object $row) => (object) $row);
    }
}