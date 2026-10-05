<?php

namespace App\Models\Concerns;

use App\Support\IdSequence;

/**
 * ให้โมเดลสร้าง primary key แบบ CHAR(11) อัตโนมัติ เช่น ZON00000001
 * โมเดลที่ใช้ trait นี้ต้องประกาศ primaryKey, keyType และ incrementing ให้ถูกต้อง
 */
trait HasGeneratedId
{
    public static function bootHasGeneratedId(): void
    {
        static::creating(function ($model) {
            $key = $model->getKeyName();

            if (blank($model->getAttribute($key))) {
                $model->setAttribute($key, IdSequence::next($model->generatedIdPrefix(), $model->getTable(), $key));
            }
        });
    }

    /**
     * รหัสนำหน้า 3 ตัวอักษรของแต่ละตาราง
     */
    abstract public static function idPrefix(): string;

    /**
     * prefix ที่ใช้ออกรหัสจริง (ค่าเริ่มต้นคือ idPrefix)
     * โมเดลที่ต้องการแยกลำดับตามช่วงเวลา เช่น EMP + ปีเดือน ให้ override เมธอดนี้
     */
    public static function generatedIdPrefix(): string
    {
        return static::idPrefix();
    }
}
