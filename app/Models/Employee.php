<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedId;
use App\Support\AnimalAvatar;
use App\Support\IdSequence;
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
 * @property ?string $employee_original_email
 * @property string $employee_password
 * @property string $employee_role
 * @property string $employee_status
 * @property bool $first_login
 * @property string|null $employee_avatar
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
        'employee_original_email',
        'employee_password',
        'employee_role',
        'employee_status',
        'first_login',
        'employee_avatar',
        'department_id',
    ];

    protected $hidden = [
        'employee_password',
    ];

    protected function casts(): array
    {
        return [
            'employee_password' => 'hashed',
            'first_login' => 'boolean',
        ];
    }

    public static function idPrefix(): string
    {
        return 'EMP';
    }

    /**
     * รหัสพนักงานรูปแบบ EMP + ปี(2 หลัก) + เดือน(2 หลัก) + ลำดับ(4 หลัก)
     * เช่น EMP26100001 = พนักงานคนที่ 1 ที่สร้างในเดือนตุลาคม พ.ศ. 2026
     *
     * คงความยาว 11 หลักตาม schema เดิม จึงไม่ต้องแก้ primary key / foreign key
     * และลำดับจะเริ่มนับใหม่ทุกเดือน
     */
    public static function generatedIdPrefix(): string
    {
        return self::idPrefix().now()->format('ym');
    }

    /**
     * รหัสถัดไปที่จะได้รับ (ใช้แสดงตัวอย่างในฟอร์มสร้างพนักงาน โดยไม่กินเลขจริง)
     */
    public static function nextIdPreview(?string $prefix = null): string
    {
        $prefix ??= static::generatedIdPrefix();

        $latest = static::query()
            ->where('employee_id', 'like', $prefix.'%')
            ->orderByDesc('employee_id')
            ->value('employee_id');

        $sequence = $latest ? ((int) substr((string) $latest, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, IdSequence::WIDTH - strlen($prefix), '0', STR_PAD_LEFT);
    }

    /**
     * ยังต้องตั้งค่าบัญชีครั้งแรกหรือไม่ (ผูกอีเมล/เปลี่ยนรหัสผ่าน/เลือก avatar)
     */
    public function needsAccountSetup(): bool
    {
        return (bool) $this->first_login;
    }

    public function avatarKey(): string
    {
        return AnimalAvatar::isValid($this->employee_avatar)
            ? $this->employee_avatar
            : AnimalAvatar::fallback();
    }

    /**
     * อีเมลที่ผู้ดูแลกระทยาไว้ตอนสร้างพนักงาน (อาจเป็น null ถ้าเป็นข้อมูลเก่าที่ยังไม่เคยบันทึกไว้)
     */
    public function originalEmail(): ?string
    {
        return $this->employee_original_email !== null && $this->employee_original_email !== ''
            ? $this->employee_original_email
            : null;
    }

    /**
     * พนักงานผูกอีเมลใหม่ตอน First-Login จนอีเมลปัจจุบันไม่ตรงกับอีเมลเดิมที่บริษัทลงทะเบียนไว้
     */
    public function hasChangedBoundEmail(): bool
    {
        $original = $this->originalEmail();

        return $original !== null
            && $this->employee_email !== null
            && strcasecmp($original, $this->employee_email) !== 0;
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

    /**
     * พนักงานไม่มีชื่อแสดงแยก จึงใช้ชื่อ-นามสกุลจริง
     */
    public function displayName(): string
    {
        return $this->employee_fullname;
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
