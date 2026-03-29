<?php

namespace Tests\Feature\Console;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditRulesCommandTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $user          = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $user->id]);
    }

    // -------------------------------------------------------------------------
    // list action
    // -------------------------------------------------------------------------

    public function test_list_shows_no_rules_when_empty(): void
    {
        $this->artisan('jr:rules', ['action' => 'list'])
            ->expectsOutputToContain('No rules configured')
            ->assertExitCode(0);
    }

    public function test_list_shows_active_rules(): void
    {
        $this->project->rules()->create([
            'title'     => 'Test Rule',
            'content'   => 'Always show a diff before editing.',
            'order'     => 0,
            'is_active' => true,
        ]);

        $this->artisan('jr:rules', ['action' => 'list'])
            ->expectsOutputToContain('Test Rule')
            ->expectsOutputToContain('Always show a diff')
            ->assertExitCode(0);
    }

    public function test_list_shows_system_prompt_preview(): void
    {
        $this->project->rules()->create([
            'title'     => 'Rule 1',
            'content'   => 'Never force push.',
            'order'     => 0,
            'is_active' => true,
        ]);

        $this->artisan('jr:rules', ['action' => 'list'])
            ->expectsOutputToContain('Project Rules')
            ->expectsOutputToContain('Never force push')
            ->assertExitCode(0);
    }

    // -------------------------------------------------------------------------
    // add action
    // -------------------------------------------------------------------------

    public function test_add_creates_new_rule(): void
    {
        $this->artisan('jr:rules', ['action' => 'add'])
            ->expectsQuestion('Rule title (short description)', 'My Rule')
            ->expectsQuestion('Rule content (instruction for AI)', 'Do not modify tests.')
            ->expectsOutputToContain('added')
            ->assertExitCode(0);

        $this->assertDatabaseHas('project_rules', [
            'project_id' => $this->project->id,
            'title'      => 'My Rule',
            'content'    => 'Do not modify tests.',
        ]);
    }

    public function test_add_fails_without_title(): void
    {
        $this->artisan('jr:rules', ['action' => 'add'])
            ->expectsQuestion('Rule title (short description)', '')
            ->expectsQuestion('Rule content (instruction for AI)', '')
            ->expectsOutputToContain('required')
            ->assertExitCode(1);
    }

    // -------------------------------------------------------------------------
    // delete action
    // -------------------------------------------------------------------------

    public function test_delete_removes_rule(): void
    {
        $rule = $this->project->rules()->create([
            'title'     => 'Old Rule',
            'content'   => 'This is old.',
            'order'     => 0,
            'is_active' => true,
        ]);

        $this->artisan('jr:rules', ['action' => 'delete'])
            ->expectsChoice('Which rule to delete?', "[{$rule->id}] Old Rule", ["[{$rule->id}] Old Rule"])
            ->expectsConfirmation('Delete rule: "Old Rule"?', 'yes')
            ->expectsOutputToContain('deleted')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('project_rules', ['id' => $rule->id]);
    }

    public function test_delete_aborts_when_declined(): void
    {
        $rule = $this->project->rules()->create([
            'title'     => 'Keep Rule',
            'content'   => 'Keep this.',
            'order'     => 0,
            'is_active' => true,
        ]);

        $this->artisan('jr:rules', ['action' => 'delete'])
            ->expectsChoice('Which rule to delete?', "[{$rule->id}] Keep Rule", ["[{$rule->id}] Keep Rule"])
            ->expectsConfirmation('Delete rule: "Keep Rule"?', 'no')
            ->expectsOutputToContain('Aborted')
            ->assertExitCode(0);

        $this->assertDatabaseHas('project_rules', ['id' => $rule->id]);
    }

    // -------------------------------------------------------------------------
    // export action
    // -------------------------------------------------------------------------

    public function test_export_shows_rules_as_markdown(): void
    {
        $this->project->rules()->create([
            'title'     => 'No Force Push',
            'content'   => 'Never force push to main.',
            'order'     => 0,
            'is_active' => true,
        ]);

        $this->artisan('jr:rules', ['action' => 'export'])
            ->expectsOutputToContain('## No Force Push')
            ->expectsOutputToContain('Never force push to main.')
            ->assertExitCode(0);
    }

    // -------------------------------------------------------------------------
    // No project
    // -------------------------------------------------------------------------

    public function test_fails_when_no_project_exists(): void
    {
        Project::query()->forceDelete();

        $this->artisan('jr:rules')
            ->expectsOutputToContain('No project found')
            ->assertExitCode(1);
    }

    // -------------------------------------------------------------------------
    // Invalid action
    // -------------------------------------------------------------------------

    public function test_fails_with_invalid_action(): void
    {
        $this->artisan('jr:rules', ['action' => 'invalid_action'])
            ->expectsOutputToContain('Unknown action')
            ->assertExitCode(1);
    }

    // -------------------------------------------------------------------------
    // project option
    // -------------------------------------------------------------------------

    public function test_selects_project_by_id(): void
    {
        $this->artisan('jr:rules', ['action' => 'list', '--project' => $this->project->id])
            ->assertExitCode(0);
    }

    public function test_selects_project_by_name(): void
    {
        $this->artisan('jr:rules', ['action' => 'list', '--project' => $this->project->name])
            ->assertExitCode(0);
    }
}
