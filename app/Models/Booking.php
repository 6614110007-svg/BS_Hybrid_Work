<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedId;
use App\Support\TimeSlot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

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
        'selfie_prompt',
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
            'selfie_prompt' => 'string',
        ];
    }

    public static function idPrefix(): string
    {
        return 'BKG';
    }

    /**
     * desk_status ถูกคำนวณใหม่ทุกครั้งที่สถานะใบจองเปลี่ยน
     * ทำให้โต๊ะถูกค้นพร้อมข้อมูลที่ถูกต้องเสมอ ไม่ต้องพึ่งเฉพาะ controller
     *
     * ถ้าเปลี่ยนโต๊ะ (แก้ไขการจอง) ต้องคำนวณสถานะของโต๊ะเดิมด้วย ไม่เช่นนั้นโต๊ะที่ปล่อย
     * จะค้างสถานะ Reserved ไว้จนกว่าจะมีใบจองอื่นมาบันทึกทับ
     */
    protected static function booted(): void
    {
        $sync = function (self $booking) {
            $manager = app(\App\Services\DeskStatusManager::class);

            $manager->sync($booking->desk);

            $originalDeskId = $booking->getOriginal('desk_id');

            if ($originalDeskId !== null && $originalDeskId !== $booking->desk_id) {
                $manager->sync(Desk::find($originalDeskId));
            }
        };

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
     * ฐานเวลาที่ใช้นับ checkin_expire_at
     *
     * Same-Day Walk-in (กดจองหลังเวลาเริ่มสล็อตไปแล้ว) ต้องนับ 60 นาที
     * จาก "เวลาที่กดจอง" ไม่ใช่จากเวลาเริ่มสล็อต เช่น จองรอบบ่าย (13:00)
     * ตอน 15:00 น. ต้องได้เวลาเช็คอินถึง 16:00 น. ไม่ใช่ถูกยกเลิกทันที
     *
     * ตาราง booking ไม่มีคอลัมน์ checkin_expire_at/created_at และต้องไม่เพิ่ม Schema
     * จึงเก็บ "เวลาที่กดจอง" ไว้ใน cache (ไม่กระทบฐานข้อมูล) แทน
     * หากไม่มีค่าใน cache (ข้อมูลจาก seeder/factory หรือ cache ถูกล้าง)
     * จะใช้พฤติกรรมเดิม คือนับจากเวลาเริ่มสล็อต
     */
    public function checkinAnchor(): Carbon
    {
        $start = $this->startsAt();

        if (! $this->booking_date->isToday()) {
            return $start;
        }

        $anchor = Cache::get($this->checkinAnchorCacheKey());

        return is_string($anchor) ? Carbon::parse($anchor) : $start;
    }

    /**
     * กำหนดค่า checkin_expire_at ตอนสร้างใบจอง
     *  - Same-Day Walk-in (วันนี้และเวลาปัจจุบันเกินเวลาเริ่มสล็อต) = now() + grace
     *  - กรณีอื่น = start_time + grace
     *
     * @return Carbon เวลาที่หมดอายุของใบจอง
     */
    public function rememberCheckinAnchor(?Carbon $at = null): Carbon
    {
        $at ??= Carbon::now();

        if ($this->booking_date->isToday() && $at->gt($this->startsAt())) {
            Cache::put($this->checkinAnchorCacheKey(), $at->toIso8601String(), $this->endsAt()->addDay());
        }

        return $this->checkinDeadline();
    }

    /**
     * เลยเวลานี้ = ไม่มากดเช็คอิน = ระบบ auto-cancel
     */
    public function checkinDeadline(): Carbon
    {
        return $this->checkinAnchor()->addMinutes((int) config('booking.late_grace_minutes', 60));
    }

    private function checkinAnchorCacheKey(): string
    {
        return 'booking:checkin-anchor:'.$this->booking_id;
    }

    /**
     * ล้างเวลาที่กดจองที่บันทึกไว้ใน cache
     *
     * ต้องเรียกเมื่อแก้ไขวันที่หรือช่วงเวลาของใบจอง เพราะเดิม anchor ถูกคำนวณจาก
     * วันที่/สล็อตเดิม ถ้าไม่ล้างจะทำให้เช็คอินได้ผิดวัน
     */
    public function forgetCheckinAnchor(): void
    {
        Cache::forget($this->checkinAnchorCacheKey());
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

    /**
     * เลยเวลาเช็คอินแล้วหรือยัง (ยังไม่ได้เช็คอิน)
     *
     * ใช้ตัดสินใจก่อนบันทึกสถานะจริง เพื่อให้หน้ารายการจองซ่อนปุ่มเช็คอิน
     * ทันทีที่เวลาผ่านไป โดยไม่ต้องรอคิว AutoCancelExpiredBookings
     */
    public function isCheckinOverdue(): bool
    {
        return $this->isReserved() && Carbon::now()->gt($this->checkinDeadline());
    }

    /**
     * เปลี่ยนสถานะเป็นหมดเวลา (X) ทันทีที่เลยกำหนด แล้วปล่อยโต๊ะกลับเป็นว่าง
     *
     * ฝั่งโต๊ะถูก sync อัตโนมัติจาก Booking::booted() เมื่อ save()
     *
     * @return bool มีการเปลี่ยนสถานะหรือไม่
     */
    public function expireIfOverdue(): bool
    {
        if (! $this->isCheckinOverdue()) {
            return false;
        }

        $this->forceFill(['booking_status' => self::STATUS_EXPIRED])->save();

        return true;
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

    /**
     * แก้ไขการจองได้เฉพาะใบที่ "ยังไม่ถึงเวลาเช็คอิน"
     *
     * เงื่อนไขเดียวกับการยกเลิก (ต้องเป็นสถานะรอเช็คอิน) และเพิ่มการไม่เอาเวลาที่เลยกำหนดแล้ว
     * เพราะถึงใบจองจะยังมีสถานะเป็น "จองแล้ว" ในฐานข้อมูล แต่ถ้าเลยเวลาเช็คอินแล้ว
     * ระบบจะปิดใบจองให้อัตโนมัติ (AutoCancelExpiredBookings) การแก้ไขจึงไม่มีความหมายแล้ว
     */
    public function isAmendable(): bool
    {
        return $this->isCancelable() && ! $this->isCheckinOverdue();
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
