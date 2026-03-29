<?php

namespace Tests\Unit\Services;

use App\Contracts\ToolRunner;
use App\DTOs\ToolResult;
use App\Enums\ToolPermission;
use App\Exceptions\ToolNotFoundException;
use App\Services\ToolRegistry;
use Tests\TestCase;

class ToolRegistryTest extends TestCase
{
    private function makeRunner(string $toolName, ToolPermission $permission = ToolPermission::Read): ToolRunner
    {
        return new class($toolName, $permission) implements ToolRunner {
            public function __construct(
                private readonly string         $name,
                private readonly ToolPermission $perm,
            ) {}

            public function supports(string $tool): bool
            {
                return $tool === $this->name;
            }

            public function run(string $tool, array $params): ToolResult
            {
                return ToolResult::ok("ran {$tool}");
            }

            public function permission(): ToolPermission
            {
                return $this->perm;
            }
        };
    }

    public function test_find_returns_matching_runner(): void
    {
        $registry = new ToolRegistry();
        $registry->register($this->makeRunner('read_file'));

        $runner = $registry->find('read_file');

        $this->assertTrue($runner->supports('read_file'));
    }

    public function test_find_throws_when_tool_not_registered(): void
    {
        $registry = new ToolRegistry();

        $this->expectException(ToolNotFoundException::class);
        $registry->find('unknown_tool');
    }

    public function test_has_returns_true_for_registered_tool(): void
    {
        $registry = new ToolRegistry();
        $registry->register($this->makeRunner('write_file'));

        $this->assertTrue($registry->has('write_file'));
    }

    public function test_has_returns_false_for_unregistered_tool(): void
    {
        $registry = new ToolRegistry();

        $this->assertFalse($registry->has('unknown_tool'));
    }

    public function test_register_many_registers_all_runners(): void
    {
        $registry = new ToolRegistry();
        $registry->registerMany([
            $this->makeRunner('tool_a'),
            $this->makeRunner('tool_b'),
            $this->makeRunner('tool_c'),
        ]);

        $this->assertTrue($registry->has('tool_a'));
        $this->assertTrue($registry->has('tool_b'));
        $this->assertTrue($registry->has('tool_c'));
    }

    public function test_all_returns_all_registered_runners(): void
    {
        $registry = new ToolRegistry();
        $registry->register($this->makeRunner('tool_a'));
        $registry->register($this->makeRunner('tool_b'));

        $this->assertCount(2, $registry->all());
    }

    public function test_first_matching_runner_wins(): void
    {
        $registry = new ToolRegistry();
        $registry->register($this->makeRunner('read_file', ToolPermission::Read));
        $registry->register($this->makeRunner('read_file', ToolPermission::Write));

        $runner = $registry->find('read_file');

        $this->assertSame(ToolPermission::Read, $runner->permission());
    }
}
