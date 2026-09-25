<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Desk extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_MAINTENANCE = 'maintenance';

    protected $fillable = [
        'zone_id',
        'code',
        'label',
        'x',
        'y',
        'is_active',
        'is_maintenance',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_maintenance' => 'boolean',
            'x' => 'integer',
            'y' => 'integer',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeMaintenance(Builder $query): Builder
    {
        return $query->where('is_maintenance', true);
    }

    public function isOutOfService(): bool
    {
        return ! $this->is_active || $this->is_maintenance;
    }
}