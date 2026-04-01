<?php

namespace Tests\Unit\Tools;

use App\Enums\ToolPermission;
use App\Tools\ListDirectoryTool;
use Tests\TestCase;

class ListDirectoryToolTest extends TestCase
{
    private string $tmpDir;
    private ListDirectoryTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/jrdev_ls_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        $this->tool = new ListDirectoryTool($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tmpDir);
        parent::tearDown();
    }

    public function test_permission_is_read(): void
    {
        $this->assertSame(ToolPermission::Read, $this->tool->permission());
    }

    public function test_supports_list_directory(): void
    {
        $this->assertTrue($this->tool->supports('list_directory'));
        $this->assertFalse($this->tool->supports('read_file'));
    }

    public function test_lists_root_directory(): void
    {
        file_put_contents($this->tmpDir . '/a.php', '');
        file_put_contents($this->tmpDir . '/b.php', '');
        mkdir($this->tmpDir . '/subdir');

        $result = $this->tool->run('list_directory', ['path' => '.']);

        $this->assertTrue($result->success);
        $names = array_column($result->output['entries'], 'name');
        $this->assertContains('a.php', $names);
        $this->assertContains('b.php', $names);
        $this->assertContains('subdir', $names);
    }

    public function test_directories_listed_before_files(): void
    {
        file_put_contents($this->tmpDir . '/z_file.txt', '');
        mkdir($this->tmpDir . '/a_dir');

        $result  = $this->tool->run('list_directory', ['path' => '.']);
        $entries = $result->output['entries'];

        $this->assertSame('directory', $entries[0]['type']);
    }

    public function test_gitignore_excludes_vendor(): void
    {
        file_put_contents($this->tmpDir . '/.gitignore', "vendor\n*.log\n");
        mkdir($this->tmpDir . '/vendor');
        file_put_contents($this->tmpDir . '/app.php', '');
        file_put_contents($this->tmpDir . '/debug.log', '');

        $result = $this->tool->run('list_directory', ['path' => '.']);
        $names  = array_column($result->output['entries'], 'name');

        $this->assertNotContains('vendor', $names);
        $this->assertNotContains('debug.log', $names);
        $this->assertContains('app.php', $names);
    }

    public function test_fails_for_nonexistent_directory(): void
    {
        $result = $this->tool->run('list_directory', ['path' => 'does_not_exist']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->error);
    }

    public function test_fails_on_path_traversal(): void
    {
        $result = $this->tool->run('list_directory', ['path' => '../../etc']);

        $this->assertFalse($result->success);
    }

    public function test_lists_subdirectory(): void
    {
        mkdir($this->tmpDir . '/sub');
        file_put_contents($this->tmpDir . '/sub/file.txt', '');

        $result = $this->tool->run('list_directory', ['path' => 'sub']);

        $this->assertTrue($result->success);
        $names = array_column($result->output['entries'], 'name');
        $this->assertContains('file.txt', $names);
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
