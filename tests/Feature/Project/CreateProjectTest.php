<?php

namespace Tests\Feature\Project;

use App\Enums\OperatingMode;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_project(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name'           => 'My Laravel App',
            'description'    => 'A test project',
            'repository_url' => 'https://github.com/example/repo',
            'operating_mode' => 'manual',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('projects', [
            'user_id'        => $user->id,
            'name'           => 'My Laravel App',
            'slug'           => 'my-laravel-app',
            'operating_mode' => 'manual',
        ]);
    }

    public function test_create_project_auto_generates_slug(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'My Awesome Project',
        ])->assertCreated();

        $this->assertDatabaseHas('projects', [
            'slug' => 'my-awesome-project',
        ]);
    }

    public function test_create_project_requires_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/projects', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_project_requires_unique_name_per_user(): void
    {
        $user = User::factory()->create();
        Project::factory()->create(['user_id' => $user->id, 'name' => 'Existing Project', 'slug' => 'existing-project']);

        $this->actingAs($user)
            ->postJson('/api/projects', ['name' => 'Existing Project'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_different_users_can_have_same_project_name(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Project::factory()->create(['user_id' => $userA->id, 'name' => 'My App', 'slug' => 'my-app']);

        $this->actingAs($userB)
            ->postJson('/api/projects', ['name' => 'My App'])
            ->assertCreated();
    }

    public function test_create_project_rejects_invalid_operating_mode(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/projects', ['name' => 'Test', 'operating_mode' => 'turbo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['operating_mode']);
    }

    public function test_unauthenticated_user_cannot_create_project(): void
    {
        $this->postJson('/api/projects', ['name' => 'Test'])
            ->assertUnauthorized();
    }
}
