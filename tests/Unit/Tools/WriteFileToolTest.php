<?php

namespace Tests\Unit\Tools;

use App\Enums\ToolPermission;
use App\Tools\WriteFileTool;
use Tests\TestCase;

class WriteFileToolTest extends TestCase
{
    private string $tmpDir;
    private WriteFileTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/jrdev_write_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        $this->tool = new WriteFileTool($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->rmdirRecursive($this->tmpDir);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Basics
    // -------------------------------------------------------------------------

    public function test_supports_write_file_tool(): void
    {
        $this->assertTrue($this->tool->supports('write_file'));
        $this->assertFalse($this->tool->supports('read_file'));
    }

    public function test_permission_is_write(): void
    {
        $this->assertSame(ToolPermission::Write, $this->tool->permission());
    }

    // -------------------------------------------------------------------------
    // Preview mode (default)
    // -------------------------------------------------------------------------

    public function test_preview_returns_diff_without_writing(): void
    {
        file_put_contents($this->tmpDir . '/file.php', "<?php\n// old\n");

        $result = $this->tool->run('write_file', [
            'path'    => 'file.php',
            'content' => "<?php\n// new\n",
        ]);

        $this->assertTrue($result->success);
        $this->assertTrue($result->output['preview']);
        $this->assertStringContainsString('-// old', $result->output['diff']);
        $this->assertStringContainsString('+// new', $result->output['diff']);

        // File should not be modified
        $this->assertStringContainsString('old', file_get_contents($this->tmpDir . '/file.php'));
    }

    public function test_preview_for_new_file_shows_diff(): void
    {
        $result = $this->tool->run('write_file', [
            'path'    => 'new_file.php',
            'content' => "<?php\necho 'hello';\n",
        ]);

        $this->assertTrue($result->success);
        $this->assertTrue($result->output['preview']);
        $this->assertFalse(file_exists($this->tmpDir . '/new_file.php'));
    }

    // -------------------------------------------------------------------------
    // Apply mode
    // -------------------------------------------------------------------------

    public function test_apply_writes_new_file(): void
    {
        $result = $this->tool->run('write_file', [
            'path'    => 'new_file.php',
            'content' => "<?php\necho 'hello';\n",
            'apply'   => true,
        ]);

        $this->assertTrue($result->success);
        $this->assertTrue($result->output['applied']);
        $this->assertFileExists($this->tmpDir . '/new_file.php');
        $this->assertStringContainsString('hello', file_get_contents($this->tmpDir . '/new_file.php'));
    }

    public function test_apply_overwrites_existing_file(): void
    {
        file_put_contents($this->tmpDir . '/file.php', "old content");

        $result = $this->tool->run('write_file', [
            'path'    => 'file.php',
            'content' => "new content",
            'apply'   => true,
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('new content', file_get_contents($this->tmpDir . '/file.php'));
    }

    public function test_apply_creates_nested_directories(): void
    {
        $result = $this->tool->run('write_file', [
            'path'    => 'deep/nested/dir/file.php',
            'content' => "<?php",
            'apply'   => true,
        ]);

        $this->assertTrue($result->success);
        $this->assertFileExists($this->tmpDir . '/deep/nested/dir/file.php');
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_fails_when_path_missing(): void
    {
        $result = $this->tool->run('write_file', ['content' => 'hello']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('path', $result->error);
    }

    public function test_fails_when_content_missing(): void
    {
        $result = $this->tool->run('write_file', ['path' => 'file.php']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('content', $result->error);
    }

    public function test_fails_on_path_traversal(): void
    {
        $result = $this->tool->run('write_file', [
            'path'    => '../../etc/evil.txt',
            'content' => 'malicious',
            'apply'   => true,
        ]);

        $this->assertFalse($result->success);
    }

    // -------------------------------------------------------------------------
    // No-change diff
    // -------------------------------------------------------------------------

    public function test_diff_shows_no_changes_when_content_identical(): void
    {
        file_put_contents($this->tmpDir . '/file.php', "same content");

        $result = $this->tool->run('write_file', [
            'path'    => 'file.php',
            'content' => 'same content',
        ]);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('no changes', $result->output['diff']);
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
