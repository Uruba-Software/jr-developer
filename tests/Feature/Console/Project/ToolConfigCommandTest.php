<?php

namespace Tests\Feature\Console\Project;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolConfigCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user    = User::factory()->create();
        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'slug'    => 'my-app',
        ]);
    }

    // ── list overrides ────────────────────────────────────────────────────────

    public function test_list_shows_no_overrides_by_default(): void
    {
        $this->artisan('jr:project:tools my-app')
            ->expectsOutputToContain('none')
            ->assertSuccessful();
    }

    public function test_list_shows_set_overrides(): void
    {
        $this->artisan('jr:project:tools my-app write_file write');
        $this->artisan('jr:project:tools my-app git_push deploy');

        $this->artisan('jr:project:tools my-app')
            ->expectsOutputToContain('write_file')
            ->expectsOutputToContain('git_push')
            ->assertSuccessful();
    }

    // ── set override ──────────────────────────────────────────────────────────

    public function test_set_valid_override(): void
    {
        $this->artisan('jr:project:tools my-app write_file write')
            ->expectsOutputToContain('set to')
            ->assertSuccessful();

        $this->project->refresh();
        $this->assertSame('write', $this->project->config['tools']['write_file']);
    }

    public function test_set_rejects_invalid_permission(): void
    {
        $this->artisan('jr:project:tools my-app write_file superuser')
            ->expectsOutputToContain('Invalid permission')
            ->assertFailed();
    }

    public function test_set_rejects_destroy_permission(): void
    {
        $this->artisan('jr:project:tools my-app write_file destroy')
            ->expectsOutputToContain('always blocked')
            ->assertFailed();
    }

    public function test_set_fails_for_nonexistent_project(): void
    {
        $this->artisan('jr:project:tools nonexistent-project write_file write')
            ->expectsOutputToContain('not found')
            ->assertFailed();
    }

    // ── reset override ────────────────────────────────────────────────────────

    public function test_reset_removes_override(): void
    {
        $this->artisan('jr:project:tools my-app write_file deploy');
        $this->artisan('jr:project:tools my-app write_file --reset')
            ->expectsOutputToContain('removed')
            ->assertSuccessful();

        $this->project->refresh();
        $this->assertArrayNotHasKey('write_file', $this->project->config['tools'] ?? []);
    }

    public function test_reset_is_safe_when_override_not_set(): void
    {
        $this->artisan('jr:project:tools my-app nonexistent_tool --reset')
            ->assertSuccessful();
    }

    // ── all permission types ──────────────────────────────────────────────────

    public function test_set_read_permission(): void
    {
        $this->artisan('jr:project:tools my-app git_commit read')
            ->assertSuccessful();

        $this->project->refresh();
        $this->assertSame('read', $this->project->config['tools']['git_commit']);
    }

    public function test_set_exec_permission(): void
    {
        $this->artisan('jr:project:tools my-app run_tests exec')
            ->assertSuccessful();
    }

    public function test_set_deploy_permission(): void
    {
        $this->artisan('jr:project:tools my-app git_push deploy')
            ->assertSuccessful();
    }
}
