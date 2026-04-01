<?php

namespace Tests\Unit\Tools;

use App\Enums\ToolPermission;
use App\Tools\GitBranchTool;
use App\Tools\GitCommitTool;
use App\Tools\GitDiffTool;
use App\Tools\GitPushTool;
use App\Tools\GitStatusTool;
use Tests\TestCase;

class GitToolsTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/jrdev_git_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        // Initialize a real git repo for testing
        shell_exec("git -C {$this->tmpDir} init -b main 2>/dev/null");
        shell_exec("git -C {$this->tmpDir} config user.email 'test@test.com'");
        shell_exec("git -C {$this->tmpDir} config user.name 'Test'");
        // Initial commit so HEAD exists
        file_put_contents($this->tmpDir . '/README.md', '# Test');
        shell_exec("git -C {$this->tmpDir} add README.md");
        shell_exec("git -C {$this->tmpDir} commit -m 'Initial commit' 2>/dev/null");
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tmpDir);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // GitStatusTool
    // -------------------------------------------------------------------------

    public function test_git_status_permission_is_read(): void
    {
        $this->assertSame(ToolPermission::Read, (new GitStatusTool($this->tmpDir))->permission());
    }

    public function test_git_status_clean_repo(): void
    {
        $tool   = new GitStatusTool($this->tmpDir);
        $result = $tool->run('git_status', []);

        $this->assertTrue($result->success);
        $this->assertTrue($result->output['clean']);
        $this->assertSame('main', $result->output['branch']);
    }

    public function test_git_status_shows_untracked_file(): void
    {
        file_put_contents($this->tmpDir . '/new.php', '<?php');
        $tool   = new GitStatusTool($this->tmpDir);
        $result = $tool->run('git_status', []);

        $this->assertTrue($result->success);
        $this->assertFalse($result->output['clean']);
        $this->assertSame(1, $result->output['count']);
    }

    public function test_git_status_fails_on_non_git_dir(): void
    {
        $tool   = new GitStatusTool('/tmp');
        $result = $tool->run('git_status', []);

        $this->assertFalse($result->success);
    }

    // -------------------------------------------------------------------------
    // GitDiffTool
    // -------------------------------------------------------------------------

    public function test_git_diff_permission_is_read(): void
    {
        $this->assertSame(ToolPermission::Read, (new GitDiffTool($this->tmpDir))->permission());
    }

    public function test_git_diff_empty_on_clean_repo(): void
    {
        $tool   = new GitDiffTool($this->tmpDir);
        $result = $tool->run('git_diff', []);

        $this->assertTrue($result->success);
        $this->assertTrue($result->output['empty']);
    }

    public function test_git_diff_shows_changes(): void
    {
        file_put_contents($this->tmpDir . '/README.md', "# Test\n\nNew line");
        $tool   = new GitDiffTool($this->tmpDir);
        $result = $tool->run('git_diff', []);

        $this->assertTrue($result->success);
        $this->assertFalse($result->output['empty']);
        $this->assertStringContainsString('New line', $result->output['diff']);
    }

    // -------------------------------------------------------------------------
    // GitBranchTool
    // -------------------------------------------------------------------------

    public function test_git_branch_permission_is_write(): void
    {
        $this->assertSame(ToolPermission::Write, (new GitBranchTool($this->tmpDir))->permission());
    }

    public function test_git_branch_creates_new_branch(): void
    {
        $tool   = new GitBranchTool($this->tmpDir);
        $result = $tool->run('git_branch', ['name' => 'feature/test', 'create' => true]);

        $this->assertTrue($result->success);
        $this->assertSame('created', $result->output['action']);

        $branch = trim(shell_exec("git -C {$this->tmpDir} rev-parse --abbrev-ref HEAD"));
        $this->assertSame('feature/test', $branch);
    }

    public function test_git_branch_fails_without_name(): void
    {
        $tool   = new GitBranchTool($this->tmpDir);
        $result = $tool->run('git_branch', []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('name', $result->error);
    }

    public function test_git_branch_rejects_invalid_name(): void
    {
        $tool   = new GitBranchTool($this->tmpDir);
        $result = $tool->run('git_branch', ['name' => 'bad name!', 'create' => true]);

        $this->assertFalse($result->success);
    }

    // -------------------------------------------------------------------------
    // GitCommitTool
    // -------------------------------------------------------------------------

    public function test_git_commit_permission_is_deploy(): void
    {
        $this->assertSame(ToolPermission::Deploy, (new GitCommitTool($this->tmpDir))->permission());
    }

    public function test_git_commit_creates_commit(): void
    {
        file_put_contents($this->tmpDir . '/new.php', '<?php');
        $tool   = new GitCommitTool($this->tmpDir);
        $result = $tool->run('git_commit', ['message' => 'test: add new file']);

        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->output['hash']);
        $this->assertSame('test: add new file', $result->output['message']);
    }

    public function test_git_commit_fails_without_message(): void
    {
        $tool   = new GitCommitTool($this->tmpDir);
        $result = $tool->run('git_commit', []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('message', $result->error);
    }

    public function test_git_commit_fails_when_nothing_to_commit(): void
    {
        $tool   = new GitCommitTool($this->tmpDir);
        $result = $tool->run('git_commit', ['message' => 'empty commit']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Nothing to commit', $result->error);
    }

    // -------------------------------------------------------------------------
    // GitPushTool
    // -------------------------------------------------------------------------

    public function test_git_push_permission_is_deploy(): void
    {
        $this->assertSame(ToolPermission::Deploy, (new GitPushTool($this->tmpDir))->permission());
    }

    public function test_git_push_supports_git_push(): void
    {
        $tool = new GitPushTool($this->tmpDir);
        $this->assertTrue($tool->supports('git_push'));
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
