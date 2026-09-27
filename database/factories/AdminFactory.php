<?php

namespace Database\Factories;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    /**
     * รหัสผ่านถูก hash อัตโนมัติด้วย cast 'hashed' ของโมเดล
     */
    public const DEFAULT_PASSWORD = 'password';

    public function definition(): array
    {
        return [
            'admin_fullname' => fake()->name(),
            'admin_email' => fake()->unique()->safeEmail(),
            'admin_password' => self::DEFAULT_PASSWORD,
            'admin_status' => Admin::STATUS_ACTIVE,
        ];
    }

    public function email(string $email): static
    {
        return $this->state(fn () => ['admin_email' => $email]);
    }

    public function withPassword(string $plain): static
    {
        return $this->state(fn () => ['admin_password' => $plain]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['admin_status' => Admin::STATUS_INACTIVE]);
    }
}
