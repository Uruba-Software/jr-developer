<?php

namespace Tests\Unit\Services;

use App\Contracts\ToolRunner;
use App\DTOs\ToolResult;
use App\Enums\ToolPermission;
use App\Exceptions\ToolNotFoundException;
use App\Exceptions\ToolPermissionDeniedException;
use App\Services\ToolExecutor;
use App\Services\ToolRegistry;
use Tests\TestCase;

class ToolExecutorTest extends TestCase
{
    private function makeRegistry(array $tools): ToolRegistry
    {
        $registry = new ToolRegistry();

        foreach ($tools as $name => $permission) {
            $registry->register(new class($name, $permission) implements ToolRunner {
                public function __construct(
                    private readonly string         $toolName,
                    private readonly ToolPermission $perm,
                ) {}

                public function supports(string $tool): bool
                {
                    return $tool === $this->toolName;
                }

                public function run(string $tool, array $params): ToolResult
                {
                    return ToolResult::ok("output of {$tool}");
                }

                public function permission(): ToolPermission
                {
                    return $this->perm;
                }
            });
        }

        return $registry;
    }

    // -------------------------------------------------------------------------
    // Read permission — auto-approved
    // -------------------------------------------------------------------------

    public function test_read_tool_executes_without_granted_permissions(): void
    {
        $executor = new ToolExecutor($this->makeRegistry(['read_file' => ToolPermission::Read]));

        $result = $executor->execute('read_file');

        $this->assertTrue($result->success);
        $this->assertSame('output of read_file', $result->output);
    }

    // -------------------------------------------------------------------------
    // Write permission — requires grant
    // -------------------------------------------------------------------------

    public function test_write_tool_throws_without_permission(): void
    {
        $executor = new ToolExecutor($this->makeRegistry(['write_file' => ToolPermission::Write]));

        $this->expectException(ToolPermissionDeniedException::class);
        $executor->execute('write_file');
    }

    public function test_write_tool_executes_with_granted_write_permission(): void
    {
        $executor = new ToolExecutor($this->makeRegistry(['write_file' => ToolPermission::Write]));

        $result = $executor->execute('write_file', [], [ToolPermission::Write]);

        $this->assertTrue($result->success);
    }

    // -------------------------------------------------------------------------
    // Exec permission — requires grant
    // -------------------------------------------------------------------------

    public function test_exec_tool_throws_without_permission(): void
    {
        $executor = new ToolExecutor($this->makeRegistry(['run_bash' => ToolPermission::Exec]));

        $this->expectException(ToolPermissionDeniedException::class);
        $executor->execute('run_bash');
    }

    public function test_exec_tool_executes_with_granted_exec_permission(): void
    {
        $executor = new ToolExecutor($this->makeRegistry(['run_bash' => ToolPermission::Exec]));

        $result = $executor->execute('run_bash', [], [ToolPermission::Exec]);

        $this->assertTrue($result->success);
    }

    // -------------------------------------------------------------------------
    // Deploy permission — requires explicit grant
    // -------------------------------------------------------------------------

    public function test_deploy_tool_executes_with_granted_deploy_permission(): void
    {
        $executor = new ToolExecutor($this->makeRegistry(['deploy' => ToolPermission::Deploy]));

        $result = $executor->execute('deploy', [], [ToolPermission::Deploy]);

        $this->assertTrue($result->success);
    }

    // -------------------------------------------------------------------------
    // Destroy permission — always blocked
    // -------------------------------------------------------------------------

    public function test_destroy_tool_is_always_blocked(): void
    {
        $executor = new ToolExecutor($this->makeRegistry(['rm_rf' => ToolPermission::Destroy]));

        $this->expectException(ToolPermissionDeniedException::class);
        $executor->execute('rm_rf', [], [ToolPermission::Destroy]);
    }

    // -------------------------------------------------------------------------
    // Not found
    // -------------------------------------------------------------------------

    public function test_execute_throws_when_tool_not_found(): void
    {
        $executor = new ToolExecutor($this->makeRegistry([]));

        $this->expectException(ToolNotFoundException::class);
        $executor->execute('unknown_tool');
    }

    // -------------------------------------------------------------------------
    // isAutoApproved / isBlocked
    // -------------------------------------------------------------------------

    public function test_is_auto_approved_true_for_read_tool(): void
    {
        $executor = new ToolExecutor($this->makeRegistry(['read_file' => ToolPermission::Read]));

        $this->assertTrue($executor->isAutoApproved('read_file'));
    }

    public function test_is_auto_approved_false_for_write_tool(): void
    {
        $executor = new ToolExecutor($this->makeRegistry(['write_file' => ToolPermission::Write]));

        $this->assertFalse($executor->isAutoApproved('write_file'));
    }

    public function test_is_auto_approved_false_for_unknown_tool(): void
    {
        $executor = new ToolExecutor($this->makeRegistry([]));

        $this->assertFalse($executor->isAutoApproved('unknown'));
    }

    public function test_is_blocked_true_for_destroy_tool(): void
    {
        $executor = new ToolExecutor($this->makeRegistry(['rm_rf' => ToolPermission::Destroy]));

        $this->assertTrue($executor->isBlocked('rm_rf'));
    }

    public function test_is_blocked_false_for_read_tool(): void
    {
        $executor = new ToolExecutor($this->makeRegistry(['read_file' => ToolPermission::Read]));

        $this->assertFalse($executor->isBlocked('read_file'));
    }

    public function test_is_blocked_false_for_unknown_tool(): void
    {
        $executor = new ToolExecutor($this->makeRegistry([]));

        $this->assertFalse($executor->isBlocked('unknown'));
    }
}
