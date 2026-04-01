<?php

namespace Tests\Feature\Console\MeetingNotes;

use App\Contracts\MessagingPlatform;
use App\Models\Project;
use App\Models\User;
use App\Services\Jira\JiraService;
use App\Services\Jira\JiraServiceFactory;
use App\Services\Jira\JiraSprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SendMeetingNotesCommandTest extends TestCase
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

    private function projectWithJiraConfig(array $extra = []): Project
    {
        $user = User::factory()->create();

        return Project::factory()->create(array_merge([
            'user_id'   => $user->id,
            'is_active' => true,
            'config'    => [
                'jira'  => [
                    'board_id'  => '10',
                    'url'       => 'https://jira.example.com',
                    'username'  => 'alice@example.com',
                    'api_token' => 'token-123',
                ],
                'slack' => ['channel_id' => 'C123456'],
            ],
        ], $extra));
    }

    /**
     * Wire up a factory mock that returns a JiraSprintService mock returning null for getActiveSprint.
     */
    private function bindFactoryMock(): void
    {
        $sprintService = Mockery::mock(JiraSprintService::class);
        $sprintService->shouldReceive('getActiveSprint')->andReturn(null);

        $factory = Mockery::mock(JiraServiceFactory::class);
        $factory->shouldReceive('forProject')->andReturn(Mockery::mock(JiraService::class));
        $factory->shouldReceive('sprintForProject')->andReturn($sprintService);

        $this->app->instance(JiraServiceFactory::class, $factory);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_rejects_invalid_meeting_type(): void
    {
        $this->app->instance(MessagingPlatform::class, Mockery::mock(MessagingPlatform::class));

        $this->artisan('jr:meeting-notes --type=invalid')
            ->expectsOutputToContain('Invalid meeting type')
            ->assertFailed();
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function test_sends_sprint_review_for_active_project(): void
    {
        $messaging = Mockery::mock(MessagingPlatform::class);
        $messaging->shouldReceive('sendMessage')->once();
        $this->app->instance(MessagingPlatform::class, $messaging);

        $this->bindFactoryMock();
        $this->projectWithJiraConfig();

        $this->artisan('jr:meeting-notes --type=sprint-review')
            ->assertSuccessful();
    }

    public function test_sends_retrospective(): void
    {
        $messaging = Mockery::mock(MessagingPlatform::class);
        $messaging->shouldReceive('sendMessage')->once();
        $this->app->instance(MessagingPlatform::class, $messaging);

        $this->bindFactoryMock();
        $this->projectWithJiraConfig();

        $this->artisan('jr:meeting-notes --type=retrospective')
            ->assertSuccessful();
    }

    public function test_sends_planning_notes(): void
    {
        $messaging = Mockery::mock(MessagingPlatform::class);
        $messaging->shouldReceive('sendMessage')->once();
        $this->app->instance(MessagingPlatform::class, $messaging);

        $this->bindFactoryMock();
        $this->projectWithJiraConfig();

        $this->artisan('jr:meeting-notes --type=planning')
            ->assertSuccessful();
    }

    public function test_sends_sync_notes(): void
    {
        $messaging = Mockery::mock(MessagingPlatform::class);
        $messaging->shouldReceive('sendMessage')->once();
        $this->app->instance(MessagingPlatform::class, $messaging);

        $this->bindFactoryMock();
        $this->projectWithJiraConfig();

        $this->artisan('jr:meeting-notes --type=sync')
            ->assertSuccessful();
    }

    // ── Skipping ───────────────────────────────────────────────────────────────

    public function test_skips_project_without_board_id(): void
    {
        $messaging = Mockery::mock(MessagingPlatform::class);
        $messaging->shouldReceive('sendMessage')->never();
        $this->app->instance(MessagingPlatform::class, $messaging);

        $user = User::factory()->create();
        Project::factory()->create([
            'user_id'   => $user->id,
            'is_active' => true,
            'config'    => ['jira' => ['url' => 'https://jira.example.com']], // no board_id
        ]);

        $this->artisan('jr:meeting-notes')->assertSuccessful();
    }

    public function test_warns_when_no_active_projects(): void
    {
        $this->app->instance(MessagingPlatform::class, Mockery::mock(MessagingPlatform::class));

        $this->artisan('jr:meeting-notes')
            ->expectsOutputToContain('No active projects')
            ->assertSuccessful();
    }

    public function test_sends_for_specific_project_only(): void
    {
        $messaging = Mockery::mock(MessagingPlatform::class);
        $messaging->shouldReceive('sendMessage')->once();
        $this->app->instance(MessagingPlatform::class, $messaging);

        $this->bindFactoryMock();

        $target = $this->projectWithJiraConfig();
        $this->projectWithJiraConfig(); // second project — should be ignored

        $this->artisan("jr:meeting-notes --project={$target->id}")
            ->assertSuccessful();
    }
}
