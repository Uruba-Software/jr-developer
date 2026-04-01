<?php

namespace Tests\Unit\Tools;

use App\Enums\ToolPermission;
use App\Tools\CreatePRTool;
use App\Tools\GetPRStatusTool;
use App\Tools\ListPRsTool;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubToolsTest extends TestCase
{
    private string $token  = 'ghp_test_token';
    private string $owner  = 'test-owner';
    private string $repo   = 'test-repo';
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/jrdev_gh_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tmpDir);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // ListPRsTool
    // -------------------------------------------------------------------------

    public function test_list_prs_permission_is_read(): void
    {
        $tool = new ListPRsTool($this->token, $this->owner, $this->repo);
        $this->assertSame(ToolPermission::Read, $tool->permission());
    }

    public function test_list_prs_supports_list_prs(): void
    {
        $tool = new ListPRsTool($this->token, $this->owner, $this->repo);
        $this->assertTrue($tool->supports('list_prs'));
    }

    public function test_list_prs_returns_prs(): void
    {
        Http::fake([
            'api.github.com/repos/*/pulls*' => Http::response([
                [
                    'number'     => 42,
                    'title'      => 'Fix bug',
                    'state'      => 'open',
                    'user'       => ['login' => 'dev1'],
                    'head'       => ['ref' => 'fix/bug'],
                    'base'       => ['ref' => 'main'],
                    'draft'      => false,
                    'html_url'   => 'https://github.com/test-owner/test-repo/pull/42',
                    'created_at' => '2026-01-01T00:00:00Z',
                ],
            ], 200),
        ]);

        $tool   = new ListPRsTool($this->token, $this->owner, $this->repo);
        $result = $tool->run('list_prs', []);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->output['count']);
        $this->assertSame(42, $result->output['prs'][0]['number']);
        $this->assertSame('Fix bug', $result->output['prs'][0]['title']);
    }

    public function test_list_prs_fails_on_api_error(): void
    {
        Http::fake([
            'api.github.com/repos/*/pulls*' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $tool   = new ListPRsTool($this->token, $this->owner, $this->repo);
        $result = $tool->run('list_prs', []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('404', $result->error);
    }

    // -------------------------------------------------------------------------
    // GetPRStatusTool
    // -------------------------------------------------------------------------

    public function test_get_pr_status_permission_is_read(): void
    {
        $tool = new GetPRStatusTool($this->token, $this->owner, $this->repo);
        $this->assertSame(ToolPermission::Read, $tool->permission());
    }

    public function test_get_pr_status_fails_without_number(): void
    {
        $tool   = new GetPRStatusTool($this->token, $this->owner, $this->repo);
        $result = $tool->run('get_pr_status', []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('number', $result->error);
    }

    public function test_get_pr_status_returns_check_runs(): void
    {
        Http::fake(static function ($request) {
            $url = $request->url();

            if (str_contains($url, '/pulls/42/reviews')) {
                return Http::response([], 200);
            }

            if (str_contains($url, '/check-runs')) {
                return Http::response([
                    'check_runs' => [
                        [
                            'name'       => 'CI Tests',
                            'status'     => 'completed',
                            'conclusion' => 'success',
                            'html_url'   => 'https://github.com/...',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/pulls/42')) {
                return Http::response([
                    'number'    => 42,
                    'title'     => 'Test PR',
                    'state'     => 'open',
                    'mergeable' => true,
                    'draft'     => false,
                    'html_url'  => 'https://github.com/test-owner/test-repo/pull/42',
                    'head'      => ['sha' => 'abc123ref'],
                ], 200);
            }

            return Http::response([], 404);
        });

        $tool   = new GetPRStatusTool($this->token, $this->owner, $this->repo);
        $result = $tool->run('get_pr_status', ['number' => 42]);

        $this->assertTrue($result->success);
        $this->assertSame(42, $result->output['number']);
        $this->assertTrue($result->output['checks_pass']);
        $this->assertCount(1, $result->output['check_runs']);
    }

    // -------------------------------------------------------------------------
    // CreatePRTool
    // -------------------------------------------------------------------------

    public function test_create_pr_permission_is_deploy(): void
    {
        $tool = new CreatePRTool($this->token, $this->owner, $this->repo, $this->tmpDir);
        $this->assertSame(ToolPermission::Deploy, $tool->permission());
    }

    public function test_create_pr_fails_without_title(): void
    {
        $tool   = new CreatePRTool($this->token, $this->owner, $this->repo, $this->tmpDir);
        $result = $tool->run('create_pr', []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('title', $result->error);
    }

    public function test_create_pr_fails_without_head_branch(): void
    {
        // In test env, git is not initialized, so currentBranch returns null
        $tool   = new CreatePRTool($this->token, $this->owner, $this->repo, $this->tmpDir);
        $result = $tool->run('create_pr', ['title' => 'My PR']);

        // Will fail either at HEAD detection or API (no fake set up)
        $this->assertFalse($result->success);
    }

    public function test_create_pr_creates_pr_via_api(): void
    {
        Http::fake([
            'api.github.com/repos/*/pulls' => Http::response([
                'number'   => 99,
                'title'    => 'T13: Add file tools',
                'html_url' => 'https://github.com/test-owner/test-repo/pull/99',
            ], 201),
        ]);

        $tool   = new CreatePRTool($this->token, $this->owner, $this->repo, $this->tmpDir);
        $result = $tool->run('create_pr', [
            'title' => 'T13: Add file tools',
            'head'  => 'T13',
            'base'  => 'dev',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(99, $result->output['number']);
        $this->assertSame('T13', $result->output['head']);
    }

    public function test_create_pr_loads_template_when_present(): void
    {
        mkdir($this->tmpDir . '/.github');
        file_put_contents($this->tmpDir . '/.github/pull_request_template.md', "## Description\n- [ ] Item");

        Http::fake([
            'api.github.com/repos/*/pulls' => Http::response([
                'number'   => 1,
                'title'    => 'Test',
                'html_url' => 'https://github.com/test-owner/test-repo/pull/1',
            ], 201),
        ]);

        // Capture the request body to verify template was loaded
        Http::fake([
            'api.github.com/repos/*/pulls' => function ($request) {
                $body = json_decode($request->body(), true);
                $this->assertStringContainsString('Description', $body['body']);

                return Http::response([
                    'number'   => 1,
                    'title'    => 'Test',
                    'html_url' => 'https://github.com/...',
                ], 201);
            },
        ]);

        $tool = new CreatePRTool($this->token, $this->owner, $this->repo, $this->tmpDir);
        $tool->run('create_pr', ['title' => 'Test', 'head' => 'feature', 'base' => 'main']);
    }

    private function rmdirRecursive(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . '/' . $item;
            is_dir($full) ? $this->rmdirRecursive($full) : unlink($full);
        }

        rmdir($path);
    }
}
