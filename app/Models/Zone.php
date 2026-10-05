<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ตาราง zone — ข้อมูลโซนพื้นที่
 *
 * @property string $zone_id
 * @property string $zone_name
 * @property int $zone_total_desks
 */
class Zone extends Model
{
    /** @use HasFactory<\Database\Factories\ZoneFactory> */
    use HasFactory, HasGeneratedId;

    protected $table = 'zone';

    protected $primaryKey = 'zone_id';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'zone_name',
        'zone_description',
        'zone_image',
        'zone_total_desks',
    ];

    protected function casts(): array
    {
        return [
            'zone_total_desks' => 'integer',
        ];
    }

    public static function idPrefix(): string
    {
        return 'ZON';
    }

    /**
     * เตรียม URL รูปปกโซนสำหรับแสดงผล (null = ยังไม่มีรูป ให้ UI ใช้ placeholder)
     */
    public function imageUrl(): ?string
    {
        return $this->zone_image ? asset('storage/'.$this->zone_image) : null;
    }

    public function desks(): HasMany
    {
        return $this->hasMany(Desk::class, 'zone_id', 'zone_id');
    }

    /**
     * คำนวณ zone_total_desks ใหม่จากจำนวนโต๊ะจริงในโซน
     * ถูกเรียกอัตโนมัติจาก event ของโมเดล Desk
     */
    public function syncDeskTotal(): void
    {
        $total = $this->desks()->count();

        if ($this->zone_total_desks === $total) {
            return;
        }

        $this->forceFill(['zone_total_desks' => $total])->save();
    }

    public function usableDesks(): HasMany
    {
        return $this->hasMany(Desk::class, 'zone_id', 'zone_id')
            ->where('desk_status', '!=', Desk::STATUS_MAINTENANCE);
    }
}
