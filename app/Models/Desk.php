<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ตาราง desk — ข้อมูลโต๊ะทำงาน
 *
 * map_position เก็บพิกัดบนผังที่นั่งจำลองในรูปแบบ "x,y"
 * desk_status เก็บสถานะปัจจุบันของโต๊ะ (Available / Reserved / Checked-In / Maintenance)
 *
 * @property string $desk_id
 * @property string $desk_number
 * @property string $map_position
 * @property string $desk_status
 * @property string $zone_id
 */
class Desk extends Model
{
    /** @use HasFactory<\Database\Factories\DeskFactory> */
    use HasFactory, HasGeneratedId;

    public const STATUS_AVAILABLE = 'Available';

    public const STATUS_RESERVED = 'Reserved';

    public const STATUS_CHECKED_IN = 'Checked-In';

    public const STATUS_MAINTENANCE = 'Maintenance';

    protected $table = 'desk';

    protected $primaryKey = 'desk_id';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'desk_number',
        'map_position',
        'desk_status',
        'zone_id',
    ];

    public static function idPrefix(): string
    {
        return 'DSK';
    }

    /**
     * zone_total_desks ของโซนถูกนับใหม่ทุกครั้งที่โต๊ะถูกเพิ่ม/ลบ/ย้ายโซน
     */
    protected static function booted(): void
    {
        $sync = fn (self $desk) => $desk->zone?->syncDeskTotal();

        static::saved($sync);
        static::deleted($sync);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id', 'zone_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'desk_id', 'desk_id');
    }

    public function scopeMaintenance(Builder $query): Builder
    {
        return $query->where('desk_status', self::STATUS_MAINTENANCE);
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('desk_status', '!=', self::STATUS_MAINTENANCE);
    }

    /**
     * @return array{0:int,1:int}
     */
    public function position(): array
    {
        $parts = array_map('trim', explode(',', (string) $this->map_position));

        return [(int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0)];
    }

    public function isMaintenance(): bool
    {
        return $this->desk_status === self::STATUS_MAINTENANCE;
    }

    public function isInUse(): bool
    {
        return $this->desk_status === self::STATUS_CHECKED_IN;
    }

    public function isReserved(): bool
    {
        return $this->desk_status === self::STATUS_RESERVED;
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_AVAILABLE => 'ว่าง',
            self::STATUS_RESERVED => 'ถูกจอง',
            self::STATUS_CHECKED_IN => 'กำลังใช้งาน',
            self::STATUS_MAINTENANCE => 'ปิดซ่อมบำรุง',
        ];
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
            self::STATUS_AVAILABLE => 'bg-emerald-100 text-emerald-700',
            self::STATUS_RESERVED => 'bg-amber-100 text-amber-700',
            self::STATUS_CHECKED_IN => 'bg-sky-100 text-sky-700',
            default => 'bg-rose-100 text-rose-700',
        };
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->desk_status] ?? $this->desk_status;
    }
}
