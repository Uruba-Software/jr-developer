<?php

namespace Tests\Unit\Tools;

use App\Enums\ToolPermission;
use App\Tools\SearchInFilesTool;
use Tests\TestCase;

class SearchInFilesToolTest extends TestCase
{
    private string $tmpDir;
    private SearchInFilesTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . '/jrdev_search_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        $this->tool = new SearchInFilesTool($this->tmpDir);
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

    public function test_supports_search_in_files(): void
    {
        $this->assertTrue($this->tool->supports('search_in_files'));
    }

    public function test_fails_when_pattern_missing(): void
    {
        $result = $this->tool->run('search_in_files', []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('pattern', $result->error);
    }

    public function test_returns_empty_when_no_matches(): void
    {
        file_put_contents($this->tmpDir . '/file.php', "<?php\necho 'hello';\n");

        $result = $this->tool->run('search_in_files', ['pattern' => 'nonexistent_xyz_pattern']);

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->output['count']);
        $this->assertEmpty($result->output['matches']);
    }

    public function test_finds_pattern_in_file(): void
    {
        file_put_contents($this->tmpDir . '/app.php', "<?php\nfunction doSomething(): void {}\n");

        $result = $this->tool->run('search_in_files', ['pattern' => 'doSomething']);

        $this->assertTrue($result->success);
        $this->assertGreaterThan(0, $result->output['count']);
    }

    public function test_fails_on_path_traversal(): void
    {
        $result = $this->tool->run('search_in_files', [
            'pattern' => 'anything',
            'path'    => '../../etc',
        ]);

        $this->assertFalse($result->success);
    }

    public function test_case_insensitive_search(): void
    {
        file_put_contents($this->tmpDir . '/file.php', "<?php\n\$MyVariable = 1;\n");

        $result = $this->tool->run('search_in_files', [
            'pattern'          => 'myvariable',
            'case_insensitive' => true,
        ]);

        $this->assertTrue($result->success);
        $this->assertGreaterThan(0, $result->output['count']);
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
