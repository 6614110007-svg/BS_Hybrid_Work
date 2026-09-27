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
                $model->setAttribute($key, IdSequence::next(static::idPrefix(), $model->getTable(), $key));
            }
        });
    }

    /**
     * รหัสนำหน้า 3 ตัวอักษรของแต่ละตาราง
     */
    abstract public static function idPrefix(): string;
}
