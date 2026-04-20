<?php

namespace Tests\Unit\Services;

use App\Enums\ToolPermission;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectToolConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectToolConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProjectToolConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProjectToolConfigService();
    }

    private function makeProject(): Project
    {
        $user = User::factory()->create();
        return Project::factory()->create(['user_id' => $user->id]);
    }

    // ── getEffectivePermission() ──────────────────────────────────────────────

    public function test_returns_default_when_no_override(): void
    {
        $project = $this->makeProject();

        $perm = $this->service->getEffectivePermission($project, 'write_file', ToolPermission::Write);

        $this->assertSame(ToolPermission::Write, $perm);
    }

    public function test_returns_override_when_set(): void
    {
        $project = $this->makeProject();
        $this->service->setPermission($project, 'write_file', ToolPermission::Deploy);

        $perm = $this->service->getEffectivePermission($project, 'write_file', ToolPermission::Write);

        $this->assertSame(ToolPermission::Deploy, $perm);
    }

    public function test_returns_default_for_tool_not_in_overrides(): void
    {
        $project = $this->makeProject();
        $this->service->setPermission($project, 'write_file', ToolPermission::Deploy);

        $perm = $this->service->getEffectivePermission($project, 'git_push', ToolPermission::Exec);

        $this->assertSame(ToolPermission::Exec, $perm);
    }

    // ── setPermission() ───────────────────────────────────────────────────────

    public function test_set_permission_persists_to_database(): void
    {
        $project = $this->makeProject();
        $this->service->setPermission($project, 'git_commit', ToolPermission::Deploy);

        $project->refresh();

        $this->assertSame('deploy', $project->config['tools']['git_commit']);
    }

    public function test_set_permission_does_not_overwrite_other_overrides(): void
    {
        $project = $this->makeProject();
        $this->service->setPermission($project, 'write_file', ToolPermission::Write);
        $this->service->setPermission($project, 'git_push', ToolPermission::Deploy);

        $project->refresh();

        $this->assertSame('write', $project->config['tools']['write_file']);
        $this->assertSame('deploy', $project->config['tools']['git_push']);
    }

    // ── removePermission() ────────────────────────────────────────────────────

    public function test_remove_permission_deletes_override(): void
    {
        $project = $this->makeProject();
        $this->service->setPermission($project, 'write_file', ToolPermission::Deploy);
        $this->service->removePermission($project, 'write_file');

        $project->refresh();

        $this->assertArrayNotHasKey('write_file', $project->config['tools'] ?? []);
    }

    public function test_remove_permission_is_safe_when_not_set(): void
    {
        $project = $this->makeProject();

        // Should not throw
        $this->service->removePermission($project, 'nonexistent_tool');

        $this->assertTrue(true);
    }

    // ── getOverrides() ────────────────────────────────────────────────────────

    public function test_get_overrides_returns_empty_array_by_default(): void
    {
        $project = $this->makeProject();

        $this->assertSame([], $this->service->getOverrides($project));
    }

    public function test_get_overrides_returns_all_set_overrides(): void
    {
        $project = $this->makeProject();
        $this->service->setPermission($project, 'write_file', ToolPermission::Write);
        $this->service->setPermission($project, 'git_commit', ToolPermission::Deploy);

        $project->refresh();
        $overrides = $this->service->getOverrides($project);

        $this->assertCount(2, $overrides);
        $this->assertSame('write', $overrides['write_file']);
        $this->assertSame('deploy', $overrides['git_commit']);
    }

    // ── setOverrides() ────────────────────────────────────────────────────────

    public function test_set_overrides_replaces_all_overrides(): void
    {
        $project = $this->makeProject();
        $this->service->setPermission($project, 'old_tool', ToolPermission::Write);

        $this->service->setOverrides($project, [
            'write_file' => 'write',
            'git_push'   => 'deploy',
        ]);

        $project->refresh();
        $overrides = $this->service->getOverrides($project);

        $this->assertArrayNotHasKey('old_tool', $overrides);
        $this->assertArrayHasKey('write_file', $overrides);
        $this->assertArrayHasKey('git_push', $overrides);
    }

    public function test_set_overrides_ignores_invalid_permission_values(): void
    {
        $project = $this->makeProject();

        $this->service->setOverrides($project, [
            'valid_tool'   => 'write',
            'invalid_tool' => 'super-admin', // invalid
        ]);

        $project->refresh();
        $overrides = $this->service->getOverrides($project);

        $this->assertArrayHasKey('valid_tool', $overrides);
        $this->assertArrayNotHasKey('invalid_tool', $overrides);
    }
}
