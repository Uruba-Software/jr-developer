<?php

namespace Tests\Feature\Console\Project;

use App\Models\PlatformConnection;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelMapCommandsTest extends TestCase
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

    // ── jr:channel:map ────────────────────────────────────────────────────────

    public function test_map_channel_to_project_by_slug(): void
    {
        $this->artisan('jr:channel:map my-app slack C0123')
            ->expectsOutputToContain('mapped to')
            ->assertSuccessful();

        $this->assertDatabaseHas('platform_connections', [
            'project_id' => $this->project->id,
            'platform'   => 'slack',
            'channel_id' => 'C0123',
            'is_active'  => true,
        ]);
    }

    public function test_map_channel_to_project_by_id(): void
    {
        $this->artisan("jr:channel:map {$this->project->id} discord D001")
            ->assertSuccessful();

        $this->assertDatabaseHas('platform_connections', [
            'channel_id' => 'D001',
            'platform'   => 'discord',
        ]);
    }

    public function test_map_stores_channel_name(): void
    {
        $this->artisan('jr:channel:map my-app slack C001 --name=general')
            ->assertSuccessful();

        $this->assertDatabaseHas('platform_connections', [
            'channel_name' => 'general',
        ]);
    }

    public function test_map_fails_for_invalid_platform(): void
    {
        $this->artisan('jr:channel:map my-app teams C001')
            ->expectsOutputToContain('Invalid platform')
            ->assertFailed();
    }

    public function test_map_fails_when_channel_already_mapped_to_other_project(): void
    {
        $other = Project::factory()->create(['user_id' => $this->user->id]);
        $this->artisan("jr:channel:map {$other->id} slack C001");

        $this->artisan('jr:channel:map my-app slack C001')
            ->expectsOutputToContain('already mapped')
            ->assertFailed();
    }

    public function test_map_fails_for_nonexistent_project(): void
    {
        $this->artisan('jr:channel:map nonexistent-project slack C001')
            ->expectsOutputToContain('not found')
            ->assertFailed();
    }

    // ── jr:channel:unmap ──────────────────────────────────────────────────────

    public function test_unmap_removes_channel_mapping(): void
    {
        $this->artisan('jr:channel:map my-app slack C001');

        $this->artisan('jr:channel:unmap my-app slack C001')
            ->expectsOutputToContain('unmapped')
            ->assertSuccessful();

        $this->assertDatabaseHas('platform_connections', [
            'channel_id' => 'C001',
            'is_active'  => false,
        ]);
    }

    public function test_unmap_warns_when_no_active_mapping(): void
    {
        $this->artisan('jr:channel:unmap my-app slack C999')
            ->expectsOutputToContain('No active mapping')
            ->assertSuccessful();
    }

    public function test_unmap_fails_for_nonexistent_project(): void
    {
        $this->artisan('jr:channel:unmap nonexistent slack C001')
            ->expectsOutputToContain('not found')
            ->assertFailed();
    }
}
