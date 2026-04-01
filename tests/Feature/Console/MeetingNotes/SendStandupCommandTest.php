<?php

namespace Tests\Feature\Console\MeetingNotes;

use App\Contracts\MessagingPlatform;
use App\Models\Project;
use App\Models\User;
use App\Services\Jira\JiraService;
use App\Services\Jira\JiraServiceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SendStandupCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function bindFactoryMock(): void
    {
        $jiraService = Mockery::mock(JiraService::class);
        $jiraService->shouldReceive('searchIssues')->andReturn([]);

        $factory = Mockery::mock(JiraServiceFactory::class);
        $factory->shouldReceive('forProject')->andReturn($jiraService);

        $this->app->instance(JiraServiceFactory::class, $factory);
    }

    private function projectWithConfig(array $jira = [], array $slack = []): Project
    {
        $user = User::factory()->create();

        return Project::factory()->create([
            'user_id'   => $user->id,
            'is_active' => true,
            'config'    => [
                'jira'  => array_merge([
                    'assignee_account_id' => 'acc-123',
                    'assignee_name'       => 'Alice',
                    'url'                 => 'https://jira.example.com',
                    'username'            => 'alice@example.com',
                    'api_token'           => 'token-123',
                ], $jira),
                'slack' => array_merge(['channel_id' => 'C123456'], $slack),
            ],
        ]);
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function test_sends_standup_for_active_project_with_jira_config(): void
    {
        $messaging = Mockery::mock(MessagingPlatform::class);
        $messaging->shouldReceive('sendMessage')->once();
        $this->app->instance(MessagingPlatform::class, $messaging);

        $this->bindFactoryMock();
        $this->projectWithConfig();

        $this->artisan('jr:standup')->assertSuccessful();
    }

    public function test_sends_standup_for_specific_project_id(): void
    {
        $messaging = Mockery::mock(MessagingPlatform::class);
        $messaging->shouldReceive('sendMessage')->once();
        $this->app->instance(MessagingPlatform::class, $messaging);

        $this->bindFactoryMock();
        $project = $this->projectWithConfig();

        $this->artisan("jr:standup --project={$project->id}")->assertSuccessful();
    }

    public function test_sends_standup_to_each_configured_project(): void
    {
        $messaging = Mockery::mock(MessagingPlatform::class);
        $messaging->shouldReceive('sendMessage')->twice();
        $this->app->instance(MessagingPlatform::class, $messaging);

        $this->bindFactoryMock();
        $this->projectWithConfig();
        $this->projectWithConfig();

        $this->artisan('jr:standup')->assertSuccessful();
    }

    // ── Skipping ───────────────────────────────────────────────────────────────

    public function test_skips_project_without_jira_assignee_config(): void
    {
        $messaging = Mockery::mock(MessagingPlatform::class);
        $messaging->shouldReceive('sendMessage')->never();
        $this->app->instance(MessagingPlatform::class, $messaging);

        $user = User::factory()->create();
        Project::factory()->create([
            'user_id'   => $user->id,
            'is_active' => true,
            'config'    => [], // no jira config
        ]);

        $this->artisan('jr:standup')->assertSuccessful();
    }

    public function test_skips_inactive_projects(): void
    {
        $messaging = Mockery::mock(MessagingPlatform::class);
        $messaging->shouldReceive('sendMessage')->never();
        $this->app->instance(MessagingPlatform::class, $messaging);

        $user = User::factory()->create();
        Project::factory()->create([
            'user_id'   => $user->id,
            'is_active' => false,
            'config'    => [
                'jira'  => ['assignee_account_id' => 'acc-123'],
                'slack' => ['channel_id' => 'C999'],
            ],
        ]);

        $this->artisan('jr:standup')->assertSuccessful();
    }

    public function test_warns_when_no_projects_found(): void
    {
        $this->app->instance(MessagingPlatform::class, Mockery::mock(MessagingPlatform::class));

        $this->artisan('jr:standup')
            ->expectsOutputToContain('No active projects')
            ->assertSuccessful();
    }

    public function test_skips_project_without_slack_channel(): void
    {
        $messaging = Mockery::mock(MessagingPlatform::class);
        $messaging->shouldReceive('sendMessage')->never();
        $this->app->instance(MessagingPlatform::class, $messaging);

        config(['services.slack.channel_id' => null]);

        $user = User::factory()->create();
        Project::factory()->create([
            'user_id'   => $user->id,
            'is_active' => true,
            'config'    => [
                'jira' => ['assignee_account_id' => 'acc-123'],
                // no slack config
            ],
        ]);

        $this->artisan('jr:standup')->assertSuccessful();
    }
}
