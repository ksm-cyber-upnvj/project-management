<?php

namespace Database\Factories;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'ticket_status_id' => TicketStatus::factory(),
            'priority_id' => TicketPriority::factory(),
            'name' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'start_date' => fake()->dateTimeBetween('-1 week', 'now'),
            'due_date' => fake()->dateTimeBetween('now', '+1 month'),
            'epic_id' => null,
            'created_by' => null,
            // Note: uuid is auto-generated in the model's booted() method
        ];
    }

    /**
     * Set a specific project for the ticket.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    /**
     * Set a specific status for the ticket.
     */
    public function withStatus(TicketStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_status_id' => $status->id,
        ]);
    }

    /**
     * Set a specific epic for the ticket.
     */
    public function forEpic(Epic $epic): static
    {
        return $this->state(fn (array $attributes) => [
            'epic_id' => $epic->id,
        ]);
    }

    /**
     * Set a specific creator for the ticket.
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }

    /**
     * Set a custom UUID for testing.
     */
    public function withUuid(string $uuid): static
    {
        return $this->state(fn (array $attributes) => [
            'uuid' => $uuid,
        ]);
    }

    /**
     * Create a ticket with no due date.
     */
    public function noDueDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => null,
        ]);
    }

    /**
     * Create an overdue ticket.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => fake()->dateTimeBetween('-1 month', '-2 weeks'),
            'due_date' => fake()->dateTimeBetween('-1 week', '-1 day'),
        ]);
    }
}

