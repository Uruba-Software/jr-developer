<?php

namespace Tests\Feature\Console;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SetupCommandTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy path — full wizard, no existing user
    // -------------------------------------------------------------------------

    public function test_creates_project_with_full_wizard(): void
    {
        Http::fake();

        $this->artisan('jr:setup')
            // Step 1: No existing user — goes straight to user creation
            ->expectsQuestion('Full name', 'Test Dev')
            ->expectsQuestion('Email address', 'dev@test.com')
            ->expectsQuestion('Password', 'secret123')
            // Step 2: Project
            ->expectsQuestion('Project name', 'My App')
            ->expectsQuestion('Absolute path to the project codebase', '/tmp')
            ->expectsQuestion('Operating mode', 'manual')
            // Step 3: VCS
            ->expectsQuestion('VCS provider', 'none')
            // Step 4: Messaging
            ->expectsQuestion('Messaging platform', 'none')
            // Step 5: AI (manual mode — skipped automatically)
            // Step 6: Tool permissions
            ->expectsConfirmation('Auto-approve WRITE operations (file edits)?', 'no')
            ->expectsConfirmation('Auto-approve EXEC operations (test runs)?', 'no')
            ->expectsConfirmation('Auto-approve DEPLOY operations (commit/push)?', 'no')
            // Step 7: Rules
            ->expectsConfirmation('Load default rules?', 'yes')
            ->expectsConfirmation('Add a custom rule now?', 'no')
            ->expectsOutputToContain('Setup complete')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'dev@test.com']);
        $this->assertDatabaseHas('projects', ['name' => 'My App']);

        $project = Project::where('name', 'My App')->first();
        $this->assertNotNull($project);
        $this->assertSame('manual', $project->operating_mode->value);
        $this->assertGreaterThan(0, $project->rules()->count());
    }

    // -------------------------------------------------------------------------
    // Stops if project exists and no --force
    // -------------------------------------------------------------------------

    public function test_aborts_if_project_already_exists_without_force(): void
    {
        $user = User::factory()->create();
        Project::factory()->create(['user_id' => $user->id]);

        $this->artisan('jr:setup')
            ->expectsOutputToContain('already configured')
            ->assertExitCode(0);

        $this->assertSame(1, Project::count());
    }

    // -------------------------------------------------------------------------
    // --force flag re-runs wizard with existing user
    // -------------------------------------------------------------------------

    public function test_force_flag_allows_re_running_setup(): void
    {
        Http::fake();

        $user = User::factory()->create(['email' => 'existing@test.com']);
        Project::factory()->create(['user_id' => $user->id]);

        $this->artisan('jr:setup', ['--force' => true])
            // Existing user found — asks confirm
            ->expectsConfirmation("Create a new user? (existing: {$user->email})", 'no')
            ->expectsQuestion('Project name', 'Second App')
            ->expectsQuestion('Absolute path to the project codebase', '/tmp')
            ->expectsQuestion('Operating mode', 'manual')
            ->expectsQuestion('VCS provider', 'none')
            ->expectsQuestion('Messaging platform', 'none')
            ->expectsConfirmation('Auto-approve WRITE operations (file edits)?', 'no')
            ->expectsConfirmation('Auto-approve EXEC operations (test runs)?', 'no')
            ->expectsConfirmation('Auto-approve DEPLOY operations (commit/push)?', 'no')
            ->expectsConfirmation('Load default rules?', 'no')
            ->expectsConfirmation('Add a custom rule now?', 'no')
            ->expectsOutputToContain('Setup complete')
            ->assertExitCode(0);

        $this->assertSame(2, Project::count());
    }

    // -------------------------------------------------------------------------
    // Project type detection
    // -------------------------------------------------------------------------

    public function test_detects_laravel_project(): void
    {
        Http::fake();

        $tmpDir = sys_get_temp_dir() . '/jrdev_detect_' . uniqid();
        mkdir($tmpDir, 0755, true);
        file_put_contents($tmpDir . '/composer.json', json_encode([
            'require' => ['laravel/framework' => '^12.0'],
        ]));

        $this->artisan('jr:setup')
            ->expectsQuestion('Full name', 'Dev')
            ->expectsQuestion('Email address', 'dev2@test.com')
            ->expectsQuestion('Password', 'pass')
            ->expectsQuestion('Project name', 'Laravel App')
            ->expectsQuestion('Absolute path to the project codebase', $tmpDir)
            ->expectsQuestion('Operating mode', 'manual')
            ->expectsQuestion('VCS provider', 'none')
            ->expectsQuestion('Messaging platform', 'none')
            ->expectsConfirmation('Auto-approve WRITE operations (file edits)?', 'no')
            ->expectsConfirmation('Auto-approve EXEC operations (test runs)?', 'no')
            ->expectsConfirmation('Auto-approve DEPLOY operations (commit/push)?', 'no')
            ->expectsConfirmation('Load default rules?', 'no')
            ->expectsConfirmation('Add a custom rule now?', 'no')
            ->expectsOutputToContain('Laravel (PHP)')
            ->assertExitCode(0);

        // Cleanup
        unlink($tmpDir . '/composer.json');
        rmdir($tmpDir);
    }

    // -------------------------------------------------------------------------
    // Config is saved with tool permissions
    // -------------------------------------------------------------------------

    public function test_saves_tool_permissions_to_config(): void
    {
        Http::fake();

        $this->artisan('jr:setup')
            ->expectsQuestion('Full name', 'Config Tester')
            ->expectsQuestion('Email address', 'config@test.com')
            ->expectsQuestion('Password', 'pass')
            ->expectsQuestion('Project name', 'Config App')
            ->expectsQuestion('Absolute path to the project codebase', '/tmp')
            ->expectsQuestion('Operating mode', 'manual')
            ->expectsQuestion('VCS provider', 'none')
            ->expectsQuestion('Messaging platform', 'none')
            ->expectsConfirmation('Auto-approve WRITE operations (file edits)?', 'yes')
            ->expectsConfirmation('Auto-approve EXEC operations (test runs)?', 'no')
            ->expectsConfirmation('Auto-approve DEPLOY operations (commit/push)?', 'no')
            ->expectsConfirmation('Load default rules?', 'no')
            ->expectsConfirmation('Add a custom rule now?', 'no')
            ->assertExitCode(0);

        $project = Project::where('name', 'Config App')->first();
        $this->assertNotNull($project->config);
        $this->assertSame('auto', $project->config['tool_permissions']['write']);
        $this->assertSame('approval', $project->config['tool_permissions']['exec']);
    }
}
