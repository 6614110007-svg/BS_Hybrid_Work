<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Booking extends Model
{
    use HasFactory;

    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const CANCEL_BY_USER = 'by_user';
    public const CANCEL_AUTO_LATE = 'auto_late';

    protected $fillable = [
        'user_id',
        'desk_id',
        'time_slot_id',
        'booking_date',
        'starts_at',
        'ends_at',
        'status',
        'cancel_reason',
        'checked_in_at',
        'checked_out_at',
        'checkin_photo_path',
        'checkin_challenge',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function desk(): BelongsTo
    {
        return $this->belongsTo(Desk::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    /**
     * booking_date เป็นวันที่ของการจองตามเขตเวลาองค์กร อย่าแปลงเป็น UTC
     * จึงเก็บเป็น string และคืน Carbon ผ่าน accessor เพื่อ convenience ในการแสดงผล
     */
    protected function bookingDate(): Attribute
    {
        return Attribute::get(fn (mixed $value) => Carbon::parse($value));
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_CONFIRMED, self::STATUS_CHECKED_IN]);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('booking_date', $date);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMED, self::STATUS_CHECKED_IN]);
    }

    public function isCancelable(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }
}