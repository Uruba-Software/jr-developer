<?php

namespace Tests\Unit\Services\MeetingNotes;

use App\DTOs\JiraIssue;
use App\DTOs\StandupNote;
use App\Services\Jira\JiraService;
use App\Services\MeetingNotes\StandupGenerator;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class StandupGeneratorTest extends TestCase
{
    private JiraService $jira;
    private StandupGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jira      = Mockery::mock(JiraService::class);
        $this->generator = new StandupGenerator($this->jira);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── generate() ────────────────────────────────────────────────────────────

    public function test_generate_returns_standup_note(): void
    {
        $this->jira->shouldReceive('searchIssues')->andReturn([]);

        $note = $this->generator->generate('user-123', 'Alice');

        $this->assertInstanceOf(StandupNote::class, $note);
        $this->assertSame('Alice', $note->assigneeName);
    }

    public function test_generate_uses_provided_date(): void
    {
        $this->jira->shouldReceive('searchIssues')->andReturn([]);

        $date = Carbon::parse('2026-03-25');
        $note = $this->generator->generate('user-123', 'Alice', $date);

        $this->assertTrue($note->date->isSameDay($date));
    }

    public function test_generate_queries_jira_three_times(): void
    {
        // completed, inProgress, blockers
        $this->jira->shouldReceive('searchIssues')->times(3)->andReturn([]);

        $note = $this->generator->generate('user-123', 'Alice');

        $this->assertInstanceOf(StandupNote::class, $note);
    }

    public function test_generate_populates_completed_issues(): void
    {
        $issue = $this->makeIssue('PROJ-1', 'Finished task');

        $this->jira->shouldReceive('searchIssues')
            ->once()
            ->andReturn([$issue]);  // completed

        $this->jira->shouldReceive('searchIssues')
            ->twice()
            ->andReturn([]);        // inProgress, blockers

        $note = $this->generator->generate('user-123', 'Alice');

        $this->assertCount(1, $note->completed);
        $this->assertSame('PROJ-1', $note->completed[0]->key);
    }

    public function test_generate_populates_in_progress_and_blockers(): void
    {
        $inProg  = $this->makeIssue('PROJ-2', 'Ongoing');
        $blocker = $this->makeIssue('PROJ-3', 'Blocked by X');

        $this->jira->shouldReceive('searchIssues')
            ->once()
            ->andReturn([]);           // completed

        $this->jira->shouldReceive('searchIssues')
            ->once()
            ->andReturn([$inProg]);    // inProgress

        $this->jira->shouldReceive('searchIssues')
            ->once()
            ->andReturn([$blocker]);   // blockers

        $note = $this->generator->generate('user-123', 'Alice');

        $this->assertCount(0, $note->completed);
        $this->assertCount(1, $note->inProgress);
        $this->assertCount(1, $note->blockers);
        $this->assertSame('PROJ-2', $note->inProgress[0]->key);
        $this->assertSame('PROJ-3', $note->blockers[0]->key);
    }

    public function test_jql_contains_assignee_id(): void
    {
        $capturedJqls = [];

        $this->jira->shouldReceive('searchIssues')
            ->times(3)
            ->andReturnUsing(function (string $jql) use (&$capturedJqls) {
                $capturedJqls[] = $jql;
                return [];
            });

        $this->generator->generate('acc-42', 'Bob');

        foreach ($capturedJqls as $jql) {
            $this->assertStringContainsString('acc-42', $jql);
        }
    }

    // ── StandupNote::toSlackMessage() ─────────────────────────────────────────

    public function test_slack_message_contains_assignee_name(): void
    {
        $this->jira->shouldReceive('searchIssues')->andReturn([]);

        $note    = $this->generator->generate('user-1', 'Charlie');
        $message = $note->toSlackMessage();

        $this->assertStringContainsString('Charlie', $message);
    }

    public function test_slack_message_shows_no_completed_when_empty(): void
    {
        $this->jira->shouldReceive('searchIssues')->andReturn([]);

        $note    = $this->generator->generate('user-1', 'Alice');
        $message = $note->toSlackMessage();

        $this->assertStringContainsString('nothing completed', $message);
    }

    public function test_slack_message_shows_no_blockers_when_empty(): void
    {
        $this->jira->shouldReceive('searchIssues')->andReturn([]);

        $note    = $this->generator->generate('user-1', 'Alice');
        $message = $note->toSlackMessage();

        $this->assertStringContainsString('no blockers', $message);
    }

    public function test_slack_message_includes_issue_keys_and_summaries(): void
    {
        $issue = $this->makeIssue('PROJ-99', 'Fix the login bug');

        $this->jira->shouldReceive('searchIssues')
            ->once()->andReturn([$issue]);   // completed
        $this->jira->shouldReceive('searchIssues')
            ->twice()->andReturn([]);

        $note    = $this->generator->generate('user-1', 'Alice');
        $message = $note->toSlackMessage();

        $this->assertStringContainsString('PROJ-99', $message);
        $this->assertStringContainsString('Fix the login bug', $message);
    }

    public function test_slack_message_contains_date(): void
    {
        $this->jira->shouldReceive('searchIssues')->andReturn([]);

        $date    = Carbon::parse('2026-03-25'); // Tuesday
        $note    = $this->generator->generate('user-1', 'Alice', $date);
        $message = $note->toSlackMessage();

        $this->assertStringContainsString('25 Mar 2026', $message);
    }

    // ── StandupNote::toArray() ────────────────────────────────────────────────

    public function test_to_array_returns_expected_keys(): void
    {
        $this->jira->shouldReceive('searchIssues')->andReturn([]);

        $note  = $this->generator->generate('user-1', 'Alice');
        $array = $note->toArray();

        $this->assertArrayHasKey('date', $array);
        $this->assertArrayHasKey('assignee', $array);
        $this->assertArrayHasKey('completed', $array);
        $this->assertArrayHasKey('in_progress', $array);
        $this->assertArrayHasKey('blockers', $array);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeIssue(string $key, string $summary): JiraIssue
    {
        return new JiraIssue(
            key:         $key,
            summary:     $summary,
            status:      'In Progress',
            assignee:    'Alice',
            reporter:    null,
            priority:    'Medium',
            issueType:   'Story',
            description: null,
            sprintName:  'Sprint 1',
            labels:      [],
            dueDate:     null,
            url:         "https://jira.example.com/browse/{$key}",
            storyPoints: '3',
        );
    }
}
