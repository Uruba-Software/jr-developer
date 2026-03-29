<?php

namespace Tests\Unit\Services\MeetingNotes;

use App\DTOs\JiraIssue;
use App\DTOs\JiraSprint;
use App\DTOs\MeetingNote;
use App\Services\Jira\JiraSprintService;
use App\Services\MeetingNotes\MeetingNoteGenerator;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class MeetingNoteGeneratorTest extends TestCase
{
    private JiraSprintService $sprints;
    private MeetingNoteGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sprints   = Mockery::mock(JiraSprintService::class);
        $this->generator = new MeetingNoteGenerator($this->sprints);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── generate() ────────────────────────────────────────────────────────────

    public function test_generate_returns_meeting_note(): void
    {
        $this->sprints->shouldReceive('getActiveSprint')->andReturn(null);

        $note = $this->generator->generate(boardId: 1);

        $this->assertInstanceOf(MeetingNote::class, $note);
    }

    public function test_generate_uses_provided_meeting_type(): void
    {
        $this->sprints->shouldReceive('getActiveSprint')->andReturn(null);

        $note = $this->generator->generate(1, MeetingNote::TYPE_RETROSPECTIVE);

        $this->assertSame(MeetingNote::TYPE_RETROSPECTIVE, $note->meetingType);
    }

    public function test_generate_defaults_to_sprint_review(): void
    {
        $this->sprints->shouldReceive('getActiveSprint')->andReturn(null);

        $note = $this->generator->generate(1);

        $this->assertSame(MeetingNote::TYPE_SPRINT_REVIEW, $note->meetingType);
    }

    public function test_generate_includes_sprint_when_active(): void
    {
        $sprint = $this->makeSprint(101, 'Sprint 5');

        $this->sprints->shouldReceive('getActiveSprint')
            ->with(1)
            ->andReturn($sprint);
        $this->sprints->shouldReceive('getSprintIssues')
            ->with(101)
            ->andReturn([]);

        $note = $this->generator->generate(1);

        $this->assertNotNull($note->sprint);
        $this->assertSame('Sprint 5', $note->sprint->name);
    }

    public function test_generate_handles_no_active_sprint(): void
    {
        $this->sprints->shouldReceive('getActiveSprint')->andReturn(null);

        $note = $this->generator->generate(1);

        $this->assertNull($note->sprint);
        $this->assertEmpty($note->completed);
        $this->assertEmpty($note->inProgress);
        $this->assertEmpty($note->upcoming);
        $this->assertEmpty($note->blockers);
    }

    // ── categorisation ────────────────────────────────────────────────────────

    public function test_done_issues_go_to_completed(): void
    {
        $sprint = $this->makeSprint(1, 'Sprint 1');
        $this->sprints->shouldReceive('getActiveSprint')->andReturn($sprint);
        $this->sprints->shouldReceive('getSprintIssues')->andReturn([
            $this->makeIssue('PROJ-1', 'Done issue', 'Done'),
            $this->makeIssue('PROJ-2', 'Closed issue', 'Closed'),
            $this->makeIssue('PROJ-3', 'Resolved issue', 'Resolved'),
        ]);

        $note = $this->generator->generate(1);

        $this->assertCount(3, $note->completed);
        $this->assertEmpty($note->inProgress);
        $this->assertEmpty($note->upcoming);
    }

    public function test_in_progress_issues_categorised_correctly(): void
    {
        $sprint = $this->makeSprint(1, 'Sprint 1');
        $this->sprints->shouldReceive('getActiveSprint')->andReturn($sprint);
        $this->sprints->shouldReceive('getSprintIssues')->andReturn([
            $this->makeIssue('PROJ-4', 'In progress issue', 'In Progress'),
            $this->makeIssue('PROJ-5', 'In review issue', 'In Review'),
        ]);

        $note = $this->generator->generate(1);

        $this->assertCount(2, $note->inProgress);
        $this->assertEmpty($note->completed);
        $this->assertEmpty($note->upcoming);
    }

    public function test_blocked_label_moves_issue_to_blockers(): void
    {
        $sprint = $this->makeSprint(1, 'Sprint 1');
        $this->sprints->shouldReceive('getActiveSprint')->andReturn($sprint);
        $this->sprints->shouldReceive('getSprintIssues')->andReturn([
            $this->makeIssue('PROJ-6', 'Blocked by infra', 'In Progress', ['blocked']),
        ]);

        $note = $this->generator->generate(1);

        $this->assertEmpty($note->inProgress);
        $this->assertCount(1, $note->blockers);
        $this->assertSame('PROJ-6', $note->blockers[0]->key);
    }

    public function test_blocked_status_moves_issue_to_blockers(): void
    {
        $sprint = $this->makeSprint(1, 'Sprint 1');
        $this->sprints->shouldReceive('getActiveSprint')->andReturn($sprint);
        $this->sprints->shouldReceive('getSprintIssues')->andReturn([
            $this->makeIssue('PROJ-7', 'Totally blocked', 'Blocked'),
        ]);

        $note = $this->generator->generate(1);

        $this->assertCount(1, $note->blockers);
        $this->assertEmpty($note->inProgress);
    }

    public function test_todo_issues_go_to_upcoming(): void
    {
        $sprint = $this->makeSprint(1, 'Sprint 1');
        $this->sprints->shouldReceive('getActiveSprint')->andReturn($sprint);
        $this->sprints->shouldReceive('getSprintIssues')->andReturn([
            $this->makeIssue('PROJ-8', 'Not started', 'To Do'),
            $this->makeIssue('PROJ-9', 'Backlog item', 'Backlog'),
            $this->makeIssue('PROJ-10', 'Open', 'Open'),
        ]);

        $note = $this->generator->generate(1);

        $this->assertCount(3, $note->upcoming);
        $this->assertEmpty($note->completed);
        $this->assertEmpty($note->inProgress);
    }

    public function test_mixed_issues_categorised_correctly(): void
    {
        $sprint = $this->makeSprint(1, 'Sprint 1');
        $this->sprints->shouldReceive('getActiveSprint')->andReturn($sprint);
        $this->sprints->shouldReceive('getSprintIssues')->andReturn([
            $this->makeIssue('A', 'Done 1', 'Done'),
            $this->makeIssue('B', 'In Progress', 'In Progress'),
            $this->makeIssue('C', 'Blocked', 'In Progress', ['blocked']),
            $this->makeIssue('D', 'Todo', 'To Do'),
        ]);

        $note = $this->generator->generate(1);

        $this->assertCount(1, $note->completed);
        $this->assertCount(1, $note->inProgress);
        $this->assertCount(1, $note->blockers);
        $this->assertCount(1, $note->upcoming);
    }

    // ── MeetingNote::toSlackMessage() ─────────────────────────────────────────

    public function test_slack_message_contains_sprint_review_title(): void
    {
        $this->sprints->shouldReceive('getActiveSprint')->andReturn(null);

        $note    = $this->generator->generate(1, MeetingNote::TYPE_SPRINT_REVIEW);
        $message = $note->toSlackMessage();

        $this->assertStringContainsString('Sprint Review', $message);
    }

    public function test_slack_message_contains_retrospective_title(): void
    {
        $this->sprints->shouldReceive('getActiveSprint')->andReturn(null);

        $note    = $this->generator->generate(1, MeetingNote::TYPE_RETROSPECTIVE);
        $message = $note->toSlackMessage();

        $this->assertStringContainsString('Retrospective', $message);
    }

    public function test_slack_message_contains_sprint_name(): void
    {
        $sprint = $this->makeSprint(1, 'Sprint 12');
        $this->sprints->shouldReceive('getActiveSprint')->andReturn($sprint);
        $this->sprints->shouldReceive('getSprintIssues')->andReturn([]);

        $note    = $this->generator->generate(1);
        $message = $note->toSlackMessage();

        $this->assertStringContainsString('Sprint 12', $message);
    }

    public function test_slack_message_shows_progress_summary(): void
    {
        $sprint = $this->makeSprint(1, 'Sprint 1');
        $this->sprints->shouldReceive('getActiveSprint')->andReturn($sprint);
        $this->sprints->shouldReceive('getSprintIssues')->andReturn([
            $this->makeIssue('A', 'Done', 'Done'),
            $this->makeIssue('B', 'Todo', 'To Do'),
        ]);

        $note    = $this->generator->generate(1);
        $message = $note->toSlackMessage();

        $this->assertStringContainsString('1/2 done', $message);
    }

    public function test_slack_message_includes_issue_keys(): void
    {
        $sprint = $this->makeSprint(1, 'Sprint 1');
        $this->sprints->shouldReceive('getActiveSprint')->andReturn($sprint);
        $this->sprints->shouldReceive('getSprintIssues')->andReturn([
            $this->makeIssue('PROJ-42', 'Fix the bug', 'Done'),
        ]);

        $note    = $this->generator->generate(1);
        $message = $note->toSlackMessage();

        $this->assertStringContainsString('PROJ-42', $message);
        $this->assertStringContainsString('Fix the bug', $message);
    }

    public function test_slack_message_shows_planning_upcoming_section(): void
    {
        $sprint = $this->makeSprint(1, 'Sprint 1');
        $this->sprints->shouldReceive('getActiveSprint')->andReturn($sprint);
        $this->sprints->shouldReceive('getSprintIssues')->andReturn([
            $this->makeIssue('UP-1', 'Future task', 'To Do'),
        ]);

        $note    = $this->generator->generate(1, MeetingNote::TYPE_PLANNING);
        $message = $note->toSlackMessage();

        $this->assertStringContainsString('Up Next', $message);
        $this->assertStringContainsString('UP-1', $message);
    }

    // ── MeetingNote::toArray() ────────────────────────────────────────────────

    public function test_to_array_returns_expected_keys(): void
    {
        $this->sprints->shouldReceive('getActiveSprint')->andReturn(null);

        $note  = $this->generator->generate(1);
        $array = $note->toArray();

        $this->assertArrayHasKey('meeting_type', $array);
        $this->assertArrayHasKey('date', $array);
        $this->assertArrayHasKey('sprint', $array);
        $this->assertArrayHasKey('completed', $array);
        $this->assertArrayHasKey('in_progress', $array);
        $this->assertArrayHasKey('upcoming', $array);
        $this->assertArrayHasKey('blockers', $array);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeIssue(
        string $key,
        string $summary,
        string $status,
        array  $labels = [],
    ): JiraIssue {
        return new JiraIssue(
            key:         $key,
            summary:     $summary,
            status:      $status,
            assignee:    'Alice',
            reporter:    null,
            priority:    'Medium',
            issueType:   'Story',
            description: null,
            sprintName:  'Sprint 1',
            labels:      $labels,
            dueDate:     null,
            url:         "https://jira.example.com/browse/{$key}",
            storyPoints: '2',
        );
    }

    private function makeSprint(int $id, string $name): JiraSprint
    {
        return new JiraSprint(
            id:        $id,
            name:      $name,
            state:     'active',
            startDate: Carbon::today()->subDays(7)->toDateTimeString(),
            endDate:   Carbon::today()->addDays(7)->toDateTimeString(),
            boardId:   1,
        );
    }
}
