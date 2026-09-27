<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ตาราง employee — ข้อมูลพนักงาน
 *
 * @property string $employee_id
 * @property string $employee_fullname
 * @property string $employee_tel
 * @property string $employee_email
 * @property string $employee_password
 * @property string $employee_role
 * @property string $employee_status
 * @property string $department_id
 */
class Employee extends Actor
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory, HasGeneratedId;

    public const ROLE_EMPLOYEE = 'Employee';

    public const ROLE_ADMIN = 'Administrator';

    protected $table = 'employee';

    protected $primaryKey = 'employee_id';

    protected $fillable = [
        'employee_fullname',
        'employee_tel',
        'employee_email',
        'employee_password',
        'employee_role',
        'employee_status',
        'department_id',
    ];

    protected $hidden = [
        'employee_password',
    ];

    protected function casts(): array
    {
        return [
            'employee_password' => 'hashed',
        ];
    }

    public static function idPrefix(): string
    {
        return 'EMP';
    }

    public function getAuthPasswordName(): string
    {
        return 'employee_password';
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'employee_id', 'employee_id');
    }

    public function actorName(): string
    {
        return $this->employee_fullname;
    }

    public function actorEmail(): string
    {
        return $this->employee_email;
    }

    public function isActive(): bool
    {
        return $this->employee_status === self::STATUS_ACTIVE;
    }

    public function isAdministrator(): bool
    {
        return $this->employee_role === self::ROLE_ADMIN;
    }

    public function status(): string
    {
        return (string) $this->employee_status;
    }

    public function authGuard(): string
    {
        return 'web';
    }

    public function roleLabel(): string
    {
        return $this->isAdministrator() ? 'ผู้ดูแลระบบ' : 'พนักงาน';
    }

    public static function roleOptions(): array
    {
        return [
            self::ROLE_EMPLOYEE => 'พนักงาน',
            self::ROLE_ADMIN => 'ผู้ดูแลระบบ',
        ];
    }
}
