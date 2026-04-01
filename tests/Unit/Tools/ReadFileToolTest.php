<?php

namespace Tests\Unit\Tools;

use App\Enums\ToolPermission;
use App\Tools\ReadFileTool;
use Tests\TestCase;

class ReadFileToolTest extends TestCase
{
    private string $tmpDir;
    private ReadFileTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/jrdev_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        $this->tool = new ReadFileTool($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tmpDir);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Basics
    // -------------------------------------------------------------------------

    public function test_supports_read_file_tool_name(): void
    {
        $this->assertTrue($this->tool->supports('read_file'));
        $this->assertFalse($this->tool->supports('write_file'));
    }

    public function test_permission_is_read(): void
    {
        $this->assertSame(ToolPermission::Read, $this->tool->permission());
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_reads_small_file_entirely(): void
    {
        file_put_contents($this->tmpDir . '/hello.txt', "line1\nline2\nline3");

        $result = $this->tool->run('read_file', ['path' => 'hello.txt']);

        $this->assertTrue($result->success);
        $this->assertSame('hello.txt', $result->output['path']);
        $this->assertSame(3, $result->output['total_lines']);
        $this->assertStringContainsString('line1', $result->output['content']);
    }

    public function test_reads_section_of_large_file(): void
    {
        $lines = array_map(static fn (int $n) => "line {$n}", range(1, 300));
        file_put_contents($this->tmpDir . '/large.txt', implode("\n", $lines));

        $result = $this->tool->run('read_file', ['path' => 'large.txt']);

        $this->assertTrue($result->success);
        $this->assertSame(300, $result->output['total_lines']);
        $this->assertSame(200, $result->output['returned']);
        $this->assertTrue($result->output['has_more']);
        $this->assertSame(200, $result->output['next_offset']);
    }

    public function test_reads_file_with_offset(): void
    {
        $lines = array_map(static fn (int $n) => "line {$n}", range(1, 300));
        file_put_contents($this->tmpDir . '/large.txt', implode("\n", $lines));

        $result = $this->tool->run('read_file', ['path' => 'large.txt', 'offset' => 200]);

        $this->assertTrue($result->success);
        $this->assertSame(200, $result->output['offset']);
        $this->assertSame(100, $result->output['returned']);
        $this->assertFalse($result->output['has_more']);
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function test_fails_when_path_is_missing(): void
    {
        $result = $this->tool->run('read_file', []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('path', $result->error);
    }

    public function test_fails_when_file_does_not_exist(): void
    {
        $result = $this->tool->run('read_file', ['path' => 'nonexistent.txt']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->error);
    }

    public function test_fails_on_path_traversal(): void
    {
        $result = $this->tool->run('read_file', ['path' => '../../etc/passwd']);

        $this->assertFalse($result->success);
    }

    public function test_fails_when_path_is_directory(): void
    {
        mkdir($this->tmpDir . '/subdir');

        $result = $this->tool->run('read_file', ['path' => 'subdir']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not a file', $result->error);
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
