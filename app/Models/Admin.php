<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ตาราง admin — ผู้ดูแลระบบ
 *
 * @property string $admin_id
 * @property string $admin_fullname
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

    public function actorName(): string
    {
        return $this->admin_fullname;
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
}
