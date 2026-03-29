<?php

namespace Tests\Unit\Services\Jira;

use App\DTOs\JiraIssue;
use App\DTOs\JiraSprint;
use App\Services\Jira\JiraSprintService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JiraSprintServiceTest extends TestCase
{
    private JiraSprintService $sprintService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        $this->sprintService = new JiraSprintService(
            baseUrl: 'https://test.atlassian.net',
            username: 'user@test.com',
            apiToken: 'test_token',
        );
    }

    // -------------------------------------------------------------------------
    // getActiveSprint
    // -------------------------------------------------------------------------

    public function test_get_active_sprint_returns_sprint(): void
    {
        Http::fake([
            'test.atlassian.net/rest/agile/1.0/board/1/sprint*' => Http::response([
                'values' => [$this->sprintPayload(10, 'Sprint 5', 'active')],
                'total'  => 1,
            ], 200),
        ]);

        $sprint = $this->sprintService->getActiveSprint(1);

        $this->assertInstanceOf(JiraSprint::class, $sprint);
        $this->assertSame(10, $sprint->id);
        $this->assertSame('Sprint 5', $sprint->name);
        $this->assertSame('active', $sprint->state);
    }

    public function test_get_active_sprint_returns_null_when_none_active(): void
    {
        Http::fake([
            'test.atlassian.net/rest/agile/1.0/board/1/sprint*' => Http::response([
                'values' => [],
                'total'  => 0,
            ], 200),
        ]);

        $sprint = $this->sprintService->getActiveSprint(1);

        $this->assertNull($sprint);
    }

    public function test_get_active_sprint_returns_null_on_api_failure(): void
    {
        Http::fake([
            'test.atlassian.net/rest/agile/1.0/board/1/sprint*' => Http::response([], 404),
        ]);

        $sprint = $this->sprintService->getActiveSprint(1);

        $this->assertNull($sprint);
    }

    // -------------------------------------------------------------------------
    // getSprintIssues
    // -------------------------------------------------------------------------

    public function test_get_sprint_issues_returns_array_of_issues(): void
    {
        Http::fake([
            'test.atlassian.net/rest/agile/1.0/sprint/10/issue*' => Http::response([
                'issues' => [
                    $this->issuePayload('EK-1'),
                    $this->issuePayload('EK-2'),
                ],
                'total' => 2,
            ], 200),
        ]);

        $issues = $this->sprintService->getSprintIssues(10);

        $this->assertCount(2, $issues);
        $this->assertInstanceOf(JiraIssue::class, $issues[0]);
    }

    public function test_get_sprint_issues_returns_empty_array_on_failure(): void
    {
        Http::fake([
            'test.atlassian.net/rest/agile/1.0/sprint/10/issue*' => Http::response([], 400),
        ]);

        $issues = $this->sprintService->getSprintIssues(10);

        $this->assertEmpty($issues);
    }

    // -------------------------------------------------------------------------
    // getIssuesByAssignee
    // -------------------------------------------------------------------------

    public function test_get_issues_by_assignee_uses_jql_search(): void
    {
        Http::fake([
            'test.atlassian.net/rest/api/3/search*' => Http::response([
                'issues' => [$this->issuePayload('EK-3')],
                'total'  => 1,
            ], 200),
        ]);

        $issues = $this->sprintService->getIssuesByAssignee('account_id_123');

        $this->assertCount(1, $issues);
        $this->assertSame('EK-3', $issues[0]->key);
    }

    public function test_get_issues_by_assignee_with_sprint_filters_by_sprint(): void
    {
        Http::fake(static function ($request) {
            $query = $request->data();
            $jql   = $query['jql'] ?? '';

            // Verify sprint is included in JQL
            if (str_contains($jql, 'sprint = 10')) {
                return Http::response([
                    'issues' => [],
                    'total'  => 0,
                ], 200);
            }

            return Http::response([], 400);
        });

        $issues = $this->sprintService->getIssuesByAssignee('account_id_123', sprintId: 10);

        $this->assertEmpty($issues);
    }

    // -------------------------------------------------------------------------
    // getAllSprints
    // -------------------------------------------------------------------------

    public function test_get_all_sprints_returns_sprints(): void
    {
        Http::fake([
            'test.atlassian.net/rest/agile/1.0/board/1/sprint*' => Http::response([
                'values' => [
                    $this->sprintPayload(10, 'Sprint 5', 'active'),
                    $this->sprintPayload(11, 'Sprint 6', 'future'),
                ],
                'total' => 2,
            ], 200),
        ]);

        $sprints = $this->sprintService->getAllSprints(1);

        $this->assertCount(2, $sprints);
        $this->assertInstanceOf(JiraSprint::class, $sprints[0]);
    }

    // -------------------------------------------------------------------------
    // JiraSprint DTO
    // -------------------------------------------------------------------------

    public function test_jira_sprint_to_array(): void
    {
        $sprint = JiraSprint::fromApiResponse($this->sprintPayload(5, 'Sprint 3', 'active'));
        $array  = $sprint->toArray();

        $this->assertSame(5, $array['id']);
        $this->assertSame('Sprint 3', $array['name']);
        $this->assertSame('active', $array['state']);
        $this->assertArrayHasKey('start_date', $array);
        $this->assertArrayHasKey('end_date', $array);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function sprintPayload(int $id, string $name, string $state): array
    {
        return [
            'id'            => $id,
            'name'          => $name,
            'state'         => $state,
            'startDate'     => '2026-03-01T09:00:00.000Z',
            'endDate'       => '2026-03-14T18:00:00.000Z',
            'originBoardId' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function issuePayload(string $key): array
    {
        return [
            'key'    => $key,
            'fields' => [
                'summary'   => "Issue {$key}",
                'status'    => ['name' => 'To Do'],
                'assignee'  => ['displayName' => 'Dev User'],
                'reporter'  => ['displayName' => 'PM User'],
                'priority'  => ['name' => 'Medium'],
                'issuetype' => ['name' => 'Story'],
                'labels'    => [],
                'duedate'   => null,
            ],
        ];
    }
}
