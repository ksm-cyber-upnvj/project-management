<?php

namespace Database\Factories;

use App\Models\ExternalAccess;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExternalAccess>
 */
class ExternalAccessFactory extends Factory
{
    protected $model = ExternalAccess::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'access_token' => Str::random(32),
            'password' => Str::random(8),
            'is_active' => true,
            'last_accessed_at' => null,
            'migration_generated' => false,
        ];
    }

    /**
     * Set a specific project for the external access.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    /**
     * Indicate that the access is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the access has been accessed.
     */
    public function accessed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_accessed_at' => now(),
        ]);
    }

    /**
     * Indicate that the access was migration generated.
     */
    public function migrationGenerated(): static
    {
        return $this->state(fn (array $attributes) => [
            'migration_generated' => true,
        ]);
    }

    /**
     * Set custom token and password.
     */
    public function withCredentials(string $token, string $password): static
    {
        return $this->state(fn (array $attributes) => [
            'access_token' => $token,
            'password' => $password,
        ]);
    }
}

