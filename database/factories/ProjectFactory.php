<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'ticket_prefix' => strtoupper(fake()->unique()->lexify('???')),
            'start_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'end_date' => fake()->dateTimeBetween('now', '+3 months'),
            'pinned_date' => null,
        ];
    }

    /**
     * Indicate that the project is pinned.
     */
    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'pinned_date' => now(),
        ]);
    }

    /**
     * Indicate that the project has no end date.
     */
    public function noEndDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'end_date' => null,
        ]);
    }

    /**
     * Indicate that the project is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => fake()->dateTimeBetween('-3 months', '-2 months'),
            'end_date' => fake()->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }
}

