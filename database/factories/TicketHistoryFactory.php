<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketHistory>
 */
class TicketHistoryFactory extends Factory
{
    protected $model = TicketHistory::class;

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
            'ticket_status_id' => TicketStatus::factory(),
        ];
    }

    /**
     * Set a specific ticket for the history.
     */
    public function forTicket(Ticket $ticket): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_id' => $ticket->id,
        ]);
    }

    /**
     * Set a specific user for the history.
     */
    public function byUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Set a specific status for the history.
     */
    public function withStatus(TicketStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_status_id' => $status->id,
        ]);
    }
}

