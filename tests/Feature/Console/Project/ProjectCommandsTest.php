<?php

namespace Tests\Feature\Console\Project;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCommandsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
    }

    // ── jr:project:list ───────────────────────────────────────────────────────

    public function test_list_shows_active_projects(): void
    {
        Project::factory()->create(['user_id' => $this->user->id, 'name' => 'My App', 'is_active' => true]);

        $this->artisan('jr:project:list')
            ->expectsOutputToContain('My App')
            ->assertSuccessful();
    }

    public function test_list_excludes_inactive_by_default(): void
    {
        Project::factory()->create(['user_id' => $this->user->id, 'name' => 'Hidden App', 'is_active' => false]);

        $this->artisan('jr:project:list')
            ->expectsOutputToContain('No projects found')
            ->assertSuccessful();
    }

    public function test_list_includes_inactive_with_all_flag(): void
    {
        Project::factory()->create(['user_id' => $this->user->id, 'name' => 'Hidden App', 'is_active' => false]);

        $this->artisan('jr:project:list --all')
            ->expectsOutputToContain('Hidden App')
            ->assertSuccessful();
    }

    // ── jr:project:add ────────────────────────────────────────────────────────

    public function test_add_creates_project_with_options(): void
    {
        $this->artisan('jr:project:add --name="Test App" --path=/var/www/test --mode=agent')
            ->expectsOutputToContain('Test App')
            ->assertSuccessful();

        $this->assertDatabaseHas('projects', ['name' => 'Test App', 'is_active' => true]);
    }

    public function test_add_rejects_invalid_mode(): void
    {
        $this->artisan('jr:project:add --name="Test" --path=/tmp --mode=invalid')
            ->assertFailed();
    }

    public function test_add_rejects_duplicate_project_name(): void
    {
        Project::factory()->create([
            'user_id' => $this->user->id,
            'name'    => 'Duplicate App',
            'slug'    => 'duplicate-app',
        ]);

        $this->artisan('jr:project:add --name="Duplicate App" --path=/tmp --mode=agent')
            ->expectsOutputToContain('already exists')
            ->assertFailed();
    }

    // ── jr:project:remove ─────────────────────────────────────────────────────

    public function test_remove_deactivates_project_by_slug(): void
    {
        $project = Project::factory()->create([
            'user_id'   => $this->user->id,
            'name'      => 'Target App',
            'slug'      => 'target-app',
            'is_active' => true,
        ]);

        $this->artisan("jr:project:remove target-app")
            ->expectsOutputToContain('deactivated')
            ->assertSuccessful();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'is_active' => false]);
    }

    public function test_remove_deactivates_project_by_id(): void
    {
        $project = Project::factory()->create([
            'user_id'   => $this->user->id,
            'is_active' => true,
        ]);

        $this->artisan("jr:project:remove {$project->id}")
            ->assertSuccessful();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'is_active' => false]);
    }

    public function test_remove_fails_for_nonexistent_project(): void
    {
        $this->artisan('jr:project:remove nonexistent-slug')
            ->expectsOutputToContain('not found')
            ->assertFailed();
    }

    public function test_remove_with_delete_flag_permanently_deletes(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'name'    => 'Delete Me',
        ]);

        $this->artisan("jr:project:remove {$project->id} --delete")
            ->expectsConfirmation("Permanently delete \"{$project->name}\"? This cannot be undone.", 'yes')
            ->assertSuccessful();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_remove_with_delete_cancels_on_no(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);

        $this->artisan("jr:project:remove {$project->id} --delete")
            ->expectsConfirmation("Permanently delete \"{$project->name}\"? This cannot be undone.", 'no')
            ->assertSuccessful();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }
}
