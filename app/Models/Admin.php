<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedId;
use App\Support\AnimalAvatar;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ตาราง admin — ผู้ดูแลระบบ
 *
 * @property string $admin_id
 * @property string $admin_fullname
 * @property string|null $admin_display_name
 * @property string|null $admin_avatar
 * @property string $admin_email
 * @property string $admin_password
 * @property string $admin_status
 */
class Admin extends Actor
{
    /** @use HasFactory<\Database\Factories\AdminFactory> */
    use HasFactory, HasGeneratedId;

    protected $table = 'admin';

    protected $primaryKey = 'admin_id';

    protected $fillable = [
        'admin_fullname',
        'admin_display_name',
        'admin_avatar',
        'admin_email',
        'admin_password',
        'admin_status',
    ];

    protected $hidden = [
        'admin_password',
    ];

    protected function casts(): array
    {
        return [
            'admin_password' => 'hashed',
        ];
    }

    public static function idPrefix(): string
    {
        return 'ADM';
    }

    public function getAuthPasswordName(): string
    {
        return 'admin_password';
    }

    /**
     * ชื่อที่แสดงบนหน้าจอ (ถ้ายังไม่ตั้งชื่อแสดงชื่อ-นามสกุลจริงแทน)
     */
    public function displayName(): string
    {
        $name = trim((string) $this->admin_display_name);

        return $name !== '' ? $name : (string) $this->admin_fullname;
    }

    public function avatarKey(): string
    {
        return AnimalAvatar::isValid($this->admin_avatar)
            ? $this->admin_avatar
            : AnimalAvatar::fallback();
    }

    public function actorName(): string
    {
        return $this->displayName();
    }

    public function actorEmail(): string
    {
        return $this->admin_email;
    }

    public function isActive(): bool
    {
        return $this->admin_status === self::STATUS_ACTIVE;
    }

    public function isAdministrator(): bool
    {
        return true;
    }

    public function status(): string
    {
        return (string) $this->admin_status;
    }

    public function authGuard(): string
    {
        return 'admin';
    }

    /**
     * ผู้ดูแลระบบทุกคนมีสิทธิ์เท่ากัน แสดงเป็นป้ายตายตัว แก้ไขไม่ได้
     */
    public function roleLabel(): string
    {
        return 'ผู้ดูแลระบบ';
    }
}
