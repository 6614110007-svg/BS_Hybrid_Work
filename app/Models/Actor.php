<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Base class สำหรับผู้ใช้งานที่ล็อกอินได้ (admin + employee)
 *
 * ตารางของทั้งสองฝั่งไม่มีคอลัมน์ created_at/updated_at, remember_token
 * และ email_verified_at จึงปิด timestamps และ remember-me ออก
 */
abstract class Actor extends Authenticatable
{
    public const STATUS_ACTIVE = 'Active';

    public const STATUS_INACTIVE = 'Inactive';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $rememberTokenName = '';

    /**
     * ชื่อที่แสดงบนหน้าจอ
     */
    abstract public function actorName(): string;

    /**
     * อีเมลที่ใช้ล็อกอิน
     */
    abstract public function actorEmail(): string;

    abstract public function isActive(): bool;

    public function isAdministrator(): bool
    {
        return false;
    }

    /**
     * หน้าแรกหลังล็อกอิน ขึ้นอยู่กับสิทธิ์ของผู้ใช้
     */
    public function homeRoute(): string
    {
        return $this->isAdministrator() ? 'admin.dashboard' : 'dashboard';
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'ใช้งานอยู่',
            self::STATUS_INACTIVE => 'ระงับการใช้งาน',
        ];
    }

    public function statusLabel(): string
    {
        return static::statusOptions()[$this->status()] ?? (string) $this->status();
    }

    /**
     * คอลัมน์สถานะต่างกันระหว่าง admin กับ employee
     */
    abstract public function status(): string;

    /**
     * ชื่อ guard ที่ใช้ session ของผู้ใช้ฝั่งนี้
     */
    abstract public function authGuard(): string;
}
