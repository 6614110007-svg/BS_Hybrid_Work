<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'department_name' => fake()->unique()->randomElement([
                'ฝ่ายพัฒนาธุรกิจ',
                'ฝ่ายไอที',
                'ฝ่ายบัญชี',
                'ฝ่ายขาย',
                'ฝ่ายบุคคล',
                'ฝ่ายวิจัยและพัฒนา',
            ]),
        ];
    }

    public function name(string $name): static
    {
        return $this->state(fn () => ['department_name' => $name]);
    }
}
