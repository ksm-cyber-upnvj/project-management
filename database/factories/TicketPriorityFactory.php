<?php

namespace Database\Factories;

use App\Models\TicketPriority;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketPriority>
 */
class TicketPriorityFactory extends Factory
{
    protected $model = TicketPriority::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Priority-' . fake()->unique()->uuid(),
            'color' => fake()->hexColor(),
        ];
    }

    /**
     * Create a high priority.
     */
    public function high(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'High',
            'color' => '#ff0000',
        ]);
    }

    /**
     * Create a low priority.
     */
    public function low(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Low',
            'color' => '#00ff00',
        ]);
    }
}

