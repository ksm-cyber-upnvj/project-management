<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketComment>
 */
class TicketCommentFactory extends Factory
{
    protected $model = TicketComment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'comment' => fake()->paragraph(),
        ];
    }

    /**
     * Set a specific ticket for the comment.
     */
    public function forTicket(Ticket $ticket): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_id' => $ticket->id,
        ]);
    }

    /**
     * Set a specific user for the comment.
     */
    public function byUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create a short comment.
     */
    public function short(): static
    {
        return $this->state(fn (array $attributes) => [
            'comment' => fake()->sentence(),
        ]);
    }

    /**
     * Create a long comment.
     */
    public function long(): static
    {
        return $this->state(fn (array $attributes) => [
            'comment' => fake()->paragraphs(3, true),
        ]);
    }
}

