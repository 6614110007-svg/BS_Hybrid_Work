<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedId;
use App\Support\TimeSlot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ตาราง booking — ข้อมูลการจองโต๊ะ
 *
 * @property string $booking_id
 * @property \Illuminate\Support\Carbon $booking_date
 * @property string $time_slot
 * @property string $start_time
 * @property string $end_time
 * @property ?\Illuminate\Support\Carbon $actual_checkin_time
 * @property ?\Illuminate\Support\Carbon $actual_checkout_time
 * @property ?string $checkin_photo
 * @property string $booking_status
 * @property string $employee_id
 * @property string $desk_id
 */
class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory, HasGeneratedId;

    /** จองแล้ว รอเช็คอิน */
    public const STATUS_RESERVED = 'R';

    /** เช็คอินแล้ว กำลังใช้งาน */
    public const STATUS_CHECKED_IN = 'C';

    /** ใช้งานเสร็จสิ้น */
    public const STATUS_COMPLETED = 'COMP';

    /** หมดอายุ / ยกเลิกอัตโนมัติ */
    public const STATUS_EXPIRED = 'X';

    protected $table = 'booking';

    protected $primaryKey = 'booking_id';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'booking_date',
        'time_slot',
        'start_time',
        'end_time',
        'actual_checkin_time',
        'actual_checkout_time',
        'checkin_photo',
        'booking_status',
        'employee_id',
        'desk_id',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'actual_checkin_time' => 'datetime',
            'actual_checkout_time' => 'datetime',
        ];
    }

    public static function idPrefix(): string
    {
        return 'BKG';
    }

    /**
     * desk_status ถูกคำนวณใหม่ทุกครั้งที่สถานะใบจองเปลี่ยน
     * ทำให้โต๊ะถูกค้นพร้อมข้อมูลที่ถูกต้องเสมอ ไม่ต้องพึ่งเฉพาะ controller
     */
    protected static function booted(): void
    {
        $sync = fn (self $booking) => app(\App\Services\DeskStatusManager::class)->sync($booking->desk);

        static::saved($sync);
        static::deleted($sync);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function desk(): BelongsTo
    {
        return $this->belongsTo(Desk::class, 'desk_id', 'desk_id');
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('booking_date', $date);
    }

    /**
     * สถานะที่ยังกินโต๊ะอยู่ (จองแล้วหรือกำลังใช้งาน)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('booking_status', [self::STATUS_RESERVED, self::STATUS_CHECKED_IN]);
    }

    public function scopeForSlot(Builder $query, string $slot): Builder
    {
        return $query->where('time_slot', $slot);
    }

    public function slot(): TimeSlot
    {
        return TimeSlot::find($this->time_slot);
    }

    public function startsAt(): Carbon
    {
        return $this->slot()->openOn($this->booking_date->toDateString());
    }

    public function endsAt(): Carbon
    {
        return $this->slot()->closeOn($this->booking_date->toDateString());
    }

    /**
     * เช็คอินได้เริ่มเมื่อใด
     */
    public function checkinOpensAt(): Carbon
    {
        return $this->slot()->opensAt($this->booking_date->toDateString());
    }

    /**
     * เลยเวลานี้ = ไม่มากดเช็คอิน = ระบบ auto-cancel
     */
    public function checkinDeadline(): Carbon
    {
        return $this->slot()->deadlineOn($this->booking_date->toDateString());
    }

    public function isReserved(): bool
    {
        return $this->booking_status === self::STATUS_RESERVED;
    }

    public function isCheckedIn(): bool
    {
        return $this->booking_status === self::STATUS_CHECKED_IN;
    }

    public function isCompleted(): bool
    {
        return $this->booking_status === self::STATUS_COMPLETED;
    }

    public function isExpired(): bool
    {
        return $this->booking_status === self::STATUS_EXPIRED;
    }

    public function isActive(): bool
    {
        return $this->isReserved() || $this->isCheckedIn();
    }

    /**
     * ยกเลิกได้เฉพาะใบที่ยังรอเช็คอิน
     */
    public function isCancelable(): bool
    {
        return $this->isReserved();
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_RESERVED => 'จองแล้ว (รอเช็คอิน)',
            self::STATUS_CHECKED_IN => 'เช็คอินแล้ว',
            self::STATUS_COMPLETED => 'ใช้งานเสร็จสิ้น',
            self::STATUS_EXPIRED => 'หมดอายุ / ยกเลิก',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->booking_status] ?? $this->booking_status;
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
            self::STATUS_RESERVED => 'bg-amber-100 text-amber-700',
            self::STATUS_CHECKED_IN => 'bg-sky-100 text-sky-700',
            self::STATUS_COMPLETED => 'bg-emerald-100 text-emerald-700',
            default => 'bg-slate-200 text-slate-600',
        };
    }
}
