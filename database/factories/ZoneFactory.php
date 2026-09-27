<?php

namespace Database\Factories;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    public function definition(): array
    {
        return [
            'zone_name' => fake()->unique()->randomElement([
                'โซนติดหน้าต่าง',
                'โซนชั้น 2',
                'โซนเงียบ',
                'โซนประชุม',
                'โซนทำงานร่วม',
            ]),
        ];
    }

    public function name(string $name): static
    {
        return $this->state(fn () => ['zone_name' => $name]);
    }
}
