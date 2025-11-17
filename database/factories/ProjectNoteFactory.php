<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectNote>
 */
class ProjectNoteFactory extends Factory
{
    protected $model = ProjectNote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(2, true),
            'note_date' => fake()->dateTimeBetween('-1 month', '+1 month'),
        ];
    }

    /**
     * Set a specific project for the note.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    /**
     * Set a specific creator for the note.
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }

    /**
     * Set a specific date for the note.
     */
    public function withDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'note_date' => $date,
        ]);
    }

    /**
     * Create a note for today.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'note_date' => now(),
        ]);
    }
}

