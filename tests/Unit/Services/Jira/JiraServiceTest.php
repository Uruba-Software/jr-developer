<?php

namespace Tests\Unit\Services\Jira;

use App\DTOs\JiraIssue;
use App\Services\Jira\JiraService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JiraServiceTest extends TestCase
{
    private JiraService $jira;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        $this->jira = new JiraService(
            baseUrl: 'https://test.atlassian.net',
            username: 'user@test.com',
            apiToken: 'test_token',
        );
    }

    // -------------------------------------------------------------------------
    // getIssue
    // -------------------------------------------------------------------------

    public function test_get_issue_returns_jira_issue(): void
    {
        Http::fake([
            'test.atlassian.net/rest/api/3/issue/EK-1*' => Http::response($this->issuePayload('EK-1'), 200),
        ]);

        $issue = $this->jira->getIssue('EK-1');

        $this->assertInstanceOf(JiraIssue::class, $issue);
        $this->assertSame('EK-1', $issue->key);
        $this->assertSame('Fix login bug', $issue->summary);
        $this->assertSame('In Progress', $issue->status);
        $this->assertSame('John Dev', $issue->assignee);
    }

    public function test_get_issue_returns_null_on_404(): void
    {
        Http::fake([
            'test.atlassian.net/rest/api/3/issue/*' => Http::response([], 404),
        ]);

        $issue = $this->jira->getIssue('EK-999');

        $this->assertNull($issue);
    }

    public function test_get_issue_is_cached(): void
    {
        Http::fake([
            'test.atlassian.net/rest/api/3/issue/EK-1*' => Http::response($this->issuePayload('EK-1'), 200),
        ]);

        // First call — hits HTTP
        $this->jira->getIssue('EK-1');

        // Second call — should use cache (Http not called again)
        Http::assertSentCount(1);

        $this->jira->getIssue('EK-1');
        Http::assertSentCount(1); // still 1
    }

    // -------------------------------------------------------------------------
    // addComment
    // -------------------------------------------------------------------------

    public function test_add_comment_returns_true_on_success(): void
    {
        Http::fake([
            'test.atlassian.net/rest/api/3/issue/EK-1/comment' => Http::response(['id' => '10000'], 201),
        ]);

        $result = $this->jira->addComment('EK-1', 'PR merged and deployed.');

        $this->assertTrue($result);
    }

    public function test_add_comment_returns_false_on_failure(): void
    {
        Http::fake([
            'test.atlassian.net/rest/api/3/issue/EK-1/comment' => Http::response([], 403),
        ]);

        $result = $this->jira->addComment('EK-1', 'comment');

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // updateStatus
    // -------------------------------------------------------------------------

    public function test_update_status_by_id_returns_true_on_success(): void
    {
        Http::fake([
            'test.atlassian.net/rest/api/3/issue/EK-1/transitions' => Http::response([], 204),
        ]);

        $result = $this->jira->updateStatus('EK-1', '31');

        $this->assertTrue($result);
    }

    public function test_update_status_by_name_resolves_transition_id(): void
    {
        Http::fake(static function ($request) {
            $url = $request->url();

            // GET transitions
            if ($request->method() === 'GET' && str_contains($url, '/transitions')) {
                return Http::response([
                    'transitions' => [
                        ['id' => '21', 'name' => 'To Do'],
                        ['id' => '31', 'name' => 'In Progress'],
                        ['id' => '41', 'name' => 'Done'],
                    ],
                ], 200);
            }

            // POST transition
            if ($request->method() === 'POST') {
                return Http::response([], 204);
            }

            return Http::response([], 404);
        });

        $result = $this->jira->updateStatus('EK-1', 'Done');

        $this->assertTrue($result);
    }

    public function test_update_status_returns_false_when_transition_not_found(): void
    {
        Http::fake([
            'test.atlassian.net/rest/api/3/issue/EK-1/transitions' => Http::response([
                'transitions' => [['id' => '21', 'name' => 'To Do']],
            ], 200),
        ]);

        $result = $this->jira->updateStatus('EK-1', 'Nonexistent Status');

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // searchIssues
    // -------------------------------------------------------------------------

    public function test_search_issues_returns_array_of_jira_issues(): void
    {
        Http::fake([
            'test.atlassian.net/rest/api/3/search*' => Http::response([
                'issues' => [
                    $this->issuePayload('EK-1'),
                    $this->issuePayload('EK-2', 'Another issue'),
                ],
                'total' => 2,
            ], 200),
        ]);

        $issues = $this->jira->searchIssues('project = EK AND status = "In Progress"');

        $this->assertCount(2, $issues);
        $this->assertInstanceOf(JiraIssue::class, $issues[0]);
        $this->assertSame('EK-1', $issues[0]->key);
    }

    public function test_search_issues_returns_empty_array_on_failure(): void
    {
        Http::fake([
            'test.atlassian.net/rest/api/3/search*' => Http::response([], 400),
        ]);

        $issues = $this->jira->searchIssues('invalid JQL !!!');

        $this->assertEmpty($issues);
    }

    public function test_search_issues_is_cached(): void
    {
        Http::fake([
            'test.atlassian.net/rest/api/3/search*' => Http::response([
                'issues' => [$this->issuePayload('EK-1')],
                'total'  => 1,
            ], 200),
        ]);

        $jql = 'project = EK';
        $this->jira->searchIssues($jql);
        $this->jira->searchIssues($jql); // second call

        Http::assertSentCount(1); // only one HTTP request
    }

    // -------------------------------------------------------------------------
    // JiraIssue DTO
    // -------------------------------------------------------------------------

    public function test_jira_issue_to_array_contains_all_fields(): void
    {
        $issue = JiraIssue::fromApiResponse($this->issuePayload('EK-5', 'Test issue'), 'https://test.atlassian.net');
        $array = $issue->toArray();

        $this->assertArrayHasKey('key', $array);
        $this->assertArrayHasKey('summary', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('assignee', $array);
        $this->assertArrayHasKey('url', $array);
        $this->assertSame('EK-5', $array['key']);
        $this->assertStringContainsString('EK-5', $array['url']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function issuePayload(string $key, string $summary = 'Fix login bug'): array
    {
        return [
            'key'    => $key,
            'fields' => [
                'summary'     => $summary,
                'status'      => ['name' => 'In Progress'],
                'assignee'    => ['displayName' => 'John Dev'],
                'reporter'    => ['displayName' => 'Jane PM'],
                'priority'    => ['name' => 'High'],
                'issuetype'   => ['name' => 'Bug'],
                'labels'      => ['backend'],
                'duedate'     => '2026-04-01',
                'description' => 'Login fails with OAuth.',
            ],
        ];
    }
}
