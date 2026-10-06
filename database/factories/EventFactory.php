<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'starts_at' => fake()->dateTimeBetween('+1 day', '+3 months'),
            'capacity' => fake()->numberBetween(10, 100),
        ];
    }

    public function started(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subHour(),
        ]);
    }
}
