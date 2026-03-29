<?php

namespace Database\Factories;

use App\Enums\MessagePlatform;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlatformConnection>
 */
class PlatformConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'   => Project::factory(),
            'platform'     => fake()->randomElement(MessagePlatform::cases()),
            'channel_id'   => fake()->unique()->regexify('[A-Z0-9]{9}'),
            'channel_name' => fake()->optional()->word(),
            'credentials'  => [],
            'is_active'    => true,
        ];
    }

    public function slack(): static
    {
        return $this->state(['platform' => MessagePlatform::Slack]);
    }

    public function discord(): static
    {
        return $this->state(['platform' => MessagePlatform::Discord]);
    }
}
