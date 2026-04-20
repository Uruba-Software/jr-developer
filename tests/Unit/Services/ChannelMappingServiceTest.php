<?php

namespace Tests\Unit\Services;

use App\Exceptions\ChannelAlreadyMappedException;
use App\Models\PlatformConnection;
use App\Models\Project;
use App\Models\User;
use App\Services\ChannelMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelMappingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChannelMappingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChannelMappingService();
    }

    private function makeProject(string $name = 'Test Project'): Project
    {
        $user = User::factory()->create();
        return Project::factory()->create(['user_id' => $user->id, 'name' => $name]);
    }

    // ── resolve() ─────────────────────────────────────────────────────────────

    public function test_resolve_returns_null_when_no_mapping(): void
    {
        $this->assertNull($this->service->resolve('slack', 'C999'));
    }

    public function test_resolve_returns_project_for_mapped_channel(): void
    {
        $project = $this->makeProject();
        $this->service->map($project, 'slack', 'C123');

        $resolved = $this->service->resolve('slack', 'C123');

        $this->assertNotNull($resolved);
        $this->assertSame($project->id, $resolved->id);
    }

    public function test_resolve_returns_null_for_inactive_mapping(): void
    {
        $project = $this->makeProject();
        $this->service->map($project, 'slack', 'C123');
        $this->service->unmap($project, 'slack', 'C123');

        $this->assertNull($this->service->resolve('slack', 'C123'));
    }

    // ── map() ─────────────────────────────────────────────────────────────────

    public function test_map_creates_platform_connection(): void
    {
        $project = $this->makeProject();
        $conn    = $this->service->map($project, 'slack', 'C001', 'general');

        $this->assertInstanceOf(PlatformConnection::class, $conn);
        $this->assertSame($project->id, $conn->project_id);
        $this->assertSame('slack', $conn->platform->value);
        $this->assertSame('C001', $conn->channel_id);
        $this->assertSame('general', $conn->channel_name);
        $this->assertTrue($conn->is_active);
    }

    public function test_map_is_idempotent_for_same_project(): void
    {
        $project = $this->makeProject();
        $this->service->map($project, 'slack', 'C001');
        $this->service->map($project, 'slack', 'C001'); // second call should not throw

        $this->assertDatabaseCount('platform_connections', 1);
    }

    public function test_map_throws_when_channel_already_mapped_to_other_project(): void
    {
        $projectA = $this->makeProject('Project A');
        $projectB = $this->makeProject('Project B');

        $this->service->map($projectA, 'slack', 'C001');

        $this->expectException(ChannelAlreadyMappedException::class);

        $this->service->map($projectB, 'slack', 'C001');
    }

    public function test_map_allows_same_channel_on_different_platforms(): void
    {
        $project = $this->makeProject();
        $this->service->map($project, 'slack', 'C001');
        $conn = $this->service->map($project, 'discord', 'C001');

        $this->assertSame('discord', $conn->platform->value);
        $this->assertDatabaseCount('platform_connections', 2);
    }

    public function test_map_reactivates_previously_unmapped_channel(): void
    {
        $project = $this->makeProject();
        $this->service->map($project, 'slack', 'C001');
        $this->service->unmap($project, 'slack', 'C001');

        // Remap — should not throw and should be active again
        $conn = $this->service->map($project, 'slack', 'C001');

        $this->assertTrue($conn->is_active);
    }

    // ── unmap() ───────────────────────────────────────────────────────────────

    public function test_unmap_deactivates_mapping(): void
    {
        $project = $this->makeProject();
        $this->service->map($project, 'slack', 'C001');

        $result = $this->service->unmap($project, 'slack', 'C001');

        $this->assertTrue($result);
        $this->assertDatabaseHas('platform_connections', [
            'channel_id' => 'C001',
            'is_active'  => false,
        ]);
    }

    public function test_unmap_returns_false_when_no_active_mapping(): void
    {
        $project = $this->makeProject();

        $result = $this->service->unmap($project, 'slack', 'C999');

        $this->assertFalse($result);
    }

    // ── hasMappingConflict() ──────────────────────────────────────────────────

    public function test_has_mapping_conflict_returns_true_when_channel_is_mapped(): void
    {
        $project = $this->makeProject();
        $this->service->map($project, 'slack', 'C001');

        $this->assertTrue($this->service->hasMappingConflict('slack', 'C001'));
    }

    public function test_has_mapping_conflict_returns_false_when_exclude_matches(): void
    {
        $project = $this->makeProject();
        $this->service->map($project, 'slack', 'C001');

        $this->assertFalse(
            $this->service->hasMappingConflict('slack', 'C001', excludeProjectId: $project->id)
        );
    }

    public function test_has_mapping_conflict_returns_false_for_unmapped_channel(): void
    {
        $this->assertFalse($this->service->hasMappingConflict('slack', 'C999'));
    }

    // ── getMappings() ─────────────────────────────────────────────────────────

    public function test_get_mappings_returns_active_connections_for_project(): void
    {
        $project = $this->makeProject();
        $this->service->map($project, 'slack', 'C001');
        $this->service->map($project, 'discord', 'D001');

        $mappings = $this->service->getMappings($project);

        $this->assertCount(2, $mappings);
    }

    public function test_get_mappings_excludes_inactive_connections(): void
    {
        $project = $this->makeProject();
        $this->service->map($project, 'slack', 'C001');
        $this->service->unmap($project, 'slack', 'C001');

        $mappings = $this->service->getMappings($project);

        $this->assertCount(0, $mappings);
    }

    // ── getAllMappings() ───────────────────────────────────────────────────────

    public function test_get_all_mappings_returns_all_active_across_projects(): void
    {
        $projectA = $this->makeProject('A');
        $projectB = $this->makeProject('B');

        $this->service->map($projectA, 'slack', 'C001');
        $this->service->map($projectB, 'slack', 'C002');

        $all = $this->service->getAllMappings();

        $this->assertCount(2, $all);
    }
}
