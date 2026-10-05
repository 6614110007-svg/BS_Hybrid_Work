<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedId;
use App\Support\DeskGrid;
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

    /**
     * แปลงพิกัด grid เป็นรูปแบบที่เก็บลงฐานข้อมูล
     */
    public static function formatPosition(int $column, int $row): string
    {
        return $column.','.$row;
    }

    /**
     * เลขโต๊ะถัดไปที่แนะนำ นับจากโต๊ะที่มีเลขสูงสุดในโซนนั้น
     *
     * ตัวอย่าง: โซนที่มี A01, A02, A03 จะได้ A04
     */
    public static function suggestNumber(?string $zoneId): ?string
    {
        if (! $zoneId) {
            return null;
        }

        $numbers = static::query()
            ->where('zone_id', $zoneId)
            ->pluck('desk_number');

        if ($numbers->isEmpty()) {
            return 'A01';
        }

        $latest = null;
        $latestPrefix = '';
        $latestValue = -1;
        $latestWidth = 0;

        foreach ($numbers as $number) {
            if (preg_match('/^(\D*)(\d+)$/', (string) $number, $matches) !== 1) {
                continue;
            }

            [, $prefix, $digits] = $matches;
            $value = (int) $digits;

            // เทียบเป็นตัวเลขจริง ไม่ใช่ lexicographic เพราะ A9 มากกว่า A10 ในการเรียงข้อความ
            if ($value <= $latestValue) {
                continue;
            }

            $latest = $number;
            $latestPrefix = $prefix;
            $latestValue = $value;
            $latestWidth = strlen($digits);
        }

        if ($latest === null) {
            return null;
        }

        $next = (string) ($latestValue + 1);

        // ขยายความกว้างเมื่อเลขท้ายเต็มหลัก เช่น A09 → A10
        return $latestPrefix.str_pad($next, $latestWidth, '0', STR_PAD_LEFT);
    }

    /**
     * หาช่อง grid ที่ยังว่างในโซน โดยไล่จากบนลงล่างซ้ายไปขวา
     * คืนค่า "column,row" หรือ null เมื่อผังเต็ม
     */
    public static function suggestGridPosition(?string $zoneId, int $maxColumn = DeskGrid::MAX_COLUMN, int $maxRow = DeskGrid::MAX_ROW): ?string
    {
        if (! $zoneId) {
            return null;
        }

        $taken = [];

        foreach (static::query()->where('zone_id', $zoneId)->pluck('map_position') as $value) {
            [$x, $y] = DeskGrid::parse($value);
            $taken[$x.','.$y] = true;
        }

        for ($row = 1; $row <= $maxRow; $row++) {
            for ($column = 1; $column <= $maxColumn; $column++) {
                if (! isset($taken[$column.','.$row])) {
                    return self::formatPosition($column, $row);
                }
            }
        }

        return null;
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
