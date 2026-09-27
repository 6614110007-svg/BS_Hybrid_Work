<?php

namespace Database\Factories;

use App\Models\Desk;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Desk>
 */
class DeskFactory extends Factory
{
    protected $model = Desk::class;

    public function definition(): array
    {
        return [
            'desk_number' => 'A'.fake()->unique()->numberBetween(1, 999),
            'map_position' => fake()->numberBetween(1, 8).','.fake()->numberBetween(1, 6),
            'desk_status' => Desk::STATUS_AVAILABLE,
            'zone_id' => Zone::factory(),
        ];
    }

    public function number(string $number): static
    {
        return $this->state(fn () => ['desk_number' => $number]);
    }

    public function position(int $x, int $y): static
    {
        return $this->state(fn () => ['map_position' => $x.','.$y]);
    }

    public function maintenance(): static
    {
        return $this->state(fn () => ['desk_status' => Desk::STATUS_MAINTENANCE]);
    }
}
