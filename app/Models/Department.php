<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ตาราง department — ข้อมูลแผนก
 *
 * @property string $department_id
 * @property string $department_name
 */
class Department extends Model
{
    /** @use HasFactory<\Database\Factories\DepartmentFactory> */
    use HasFactory, HasGeneratedId;

    protected $table = 'department';

    protected $primaryKey = 'department_id';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'department_name',
    ];

    public static function idPrefix(): string
    {
        return 'DEP';
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'department_id', 'department_id');
    }
}
