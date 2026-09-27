<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public const DEFAULT_PASSWORD = 'password';

    public function definition(): array
    {
        return [
            'employee_fullname' => fake()->name(),
            'employee_tel' => '0'.fake()->numerify('#########'),
            'employee_email' => fake()->unique()->safeEmail(),
            'employee_password' => self::DEFAULT_PASSWORD,
            'employee_role' => Employee::ROLE_EMPLOYEE,
            'employee_status' => Employee::STATUS_ACTIVE,
            'department_id' => Department::factory(),
        ];
    }

    public function email(string $email): static
    {
        return $this->state(fn () => ['employee_email' => $email]);
    }

    public function withPassword(string $plain): static
    {
        return $this->state(fn () => ['employee_password' => $plain]);
    }

    public function administrator(): static
    {
        return $this->state(fn () => ['employee_role' => Employee::ROLE_ADMIN]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['employee_status' => Employee::STATUS_INACTIVE]);
    }
}
