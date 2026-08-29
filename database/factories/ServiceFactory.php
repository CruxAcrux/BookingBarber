<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Haircut', 'Beard trim', 'Hot towel shave', 'Cut and shave']),
            'duration_minutes' => fake()->randomElement([30, 45, 60]),
            'price_cents' => fake()->numberBetween(1500, 6000),
        ];
    }

    public function lasting(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'duration_minutes' => $minutes,
        ]);
    }
}
