<?php

namespace Database\Factories;

use App\Enums\OperatingMode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'user_id'        => User::factory(),
            'name'           => ucwords($name),
            'slug'           => Str::slug($name),
            'description'    => fake()->optional()->sentence(),
            'repository_url' => fake()->optional()->url(),
            'local_path'     => null,
            'default_branch' => 'main',
            'operating_mode' => OperatingMode::Manual,
            'is_active'      => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function agentMode(): static
    {
        return $this->state(['operating_mode' => OperatingMode::Agent]);
    }
}
