<?php

namespace Tests\Unit\Adapters\TestRunners;

use App\Adapters\TestRunners\PythonTestRunner;
use App\DTOs\TestResult;
use Tests\TestCase;

class PythonTestRunnerTest extends TestCase
{
    private PythonTestRunner $runner;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner  = new PythonTestRunner();
        $this->tempDir = sys_get_temp_dir() . '/jrdev_py_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDir($this->tempDir);
        if (file_exists('/tmp/jrdev_pytest_results.json')) {
            unlink('/tmp/jrdev_pytest_results.json');
        }
    }

    // ── supports() ────────────────────────────────────────────────────────────

    public function test_supports_project_with_requirements_txt(): void
    {
        touch($this->tempDir . '/requirements.txt');
        $this->assertTrue($this->runner->supports($this->tempDir));
    }

    public function test_supports_project_with_pyproject_toml(): void
    {
        touch($this->tempDir . '/pyproject.toml');
        $this->assertTrue($this->runner->supports($this->tempDir));
    }

    public function test_supports_project_with_setup_py(): void
    {
        touch($this->tempDir . '/setup.py');
        $this->assertTrue($this->runner->supports($this->tempDir));
    }

    public function test_supports_project_with_pytest_ini(): void
    {
        touch($this->tempDir . '/pytest.ini');
        $this->assertTrue($this->runner->supports($this->tempDir));
    }

    public function test_supports_project_with_setup_cfg(): void
    {
        touch($this->tempDir . '/setup.cfg');
        $this->assertTrue($this->runner->supports($this->tempDir));
    }

    public function test_does_not_support_empty_project(): void
    {
        $this->assertFalse($this->runner->supports($this->tempDir));
    }

    // ── name() ────────────────────────────────────────────────────────────────

    public function test_name_returns_expected_string(): void
    {
        $this->assertSame('Python pytest', $this->runner->name());
    }

    // ── JSON report parsing ────────────────────────────────────────────────────

    public function test_parses_json_report_success(): void
    {
        $report = [
            'summary' => ['passed' => 4, 'failed' => 0, 'error' => 0, 'skipped' => 1],
            'tests'   => [
                ['nodeid' => 'test_foo.py::test_a', 'outcome' => 'passed'],
                ['nodeid' => 'test_foo.py::test_b', 'outcome' => 'passed'],
            ],
        ];

        file_put_contents('/tmp/jrdev_pytest_results.json', json_encode($report));
        touch($this->tempDir . '/requirements.txt');

        // Fake python that exits 0 and outputs nothing (JSON file is the real output)
        $result = $this->runWithFakePython('', exitCode: 0);

        $this->assertTrue($result->success);
        $this->assertSame(4, $result->passed);
        $this->assertSame(0, $result->failed);
        $this->assertSame(1, $result->skipped);
        $this->assertSame('Python pytest', $result->runner);
    }

    public function test_parses_json_report_with_failures(): void
    {
        $report = [
            'summary' => ['passed' => 2, 'failed' => 2, 'error' => 1, 'skipped' => 0],
            'tests'   => [
                ['nodeid' => 'test_foo.py::test_a', 'outcome' => 'passed'],
                ['nodeid' => 'test_foo.py::test_b', 'outcome' => 'failed'],
                ['nodeid' => 'test_foo.py::test_c', 'outcome' => 'error'],
                ['nodeid' => 'test_foo.py::test_d', 'outcome' => 'failed'],
            ],
        ];

        file_put_contents('/tmp/jrdev_pytest_results.json', json_encode($report));
        touch($this->tempDir . '/requirements.txt');

        $result = $this->runWithFakePython('', exitCode: 1);

        $this->assertFalse($result->success);
        $this->assertSame(2, $result->passed);
        $this->assertSame(2, $result->failed);
        $this->assertSame(1, $result->errors);
        $this->assertCount(3, $result->failedTests); // failed + error
        $this->assertContains('test_foo.py::test_b', $result->failedTests);
        $this->assertContains('test_foo.py::test_c', $result->failedTests);
    }

    // ── Text output fallback ───────────────────────────────────────────────────

    public function test_falls_back_to_text_parsing(): void
    {
        touch($this->tempDir . '/requirements.txt');

        $output = "5 passed, 2 failed, 1 error in 3.21s\n"
            . "FAILED test_foo.py::test_b\n"
            . "FAILED test_foo.py::test_d\n";

        $result = $this->runWithFakePython($output, exitCode: 1);

        $this->assertFalse($result->success);
        $this->assertSame(5, $result->passed);
        $this->assertSame(2, $result->failed);
        $this->assertSame(1, $result->errors);
        $this->assertCount(2, $result->failedTests);
    }

    public function test_text_fallback_all_pass(): void
    {
        touch($this->tempDir . '/requirements.txt');
        $output = '7 passed in 1.05s';

        $result = $this->runWithFakePython($output, exitCode: 0);

        $this->assertTrue($result->success);
        $this->assertSame(7, $result->passed);
        $this->assertSame(0, $result->failed);
    }

    public function test_text_fallback_with_skipped(): void
    {
        touch($this->tempDir . '/requirements.txt');
        $output = '5 passed, 2 skipped in 0.5s';

        $result = $this->runWithFakePython($output, exitCode: 0);

        $this->assertTrue($result->success);
        $this->assertSame(5, $result->passed);
        $this->assertSame(2, $result->skipped);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function runWithFakePython(string $output, int $exitCode): TestResult
    {
        // We can't easily replace `python` on the system path, so we test via
        // injecting a JSON file when present, or test text output indirectly.
        // Instead, exercise the public `run()` entry point by writing a fake
        // python wrapper into PATH using a shell wrapper script.

        $fakeBin = $this->tempDir . '/python';
        file_put_contents($fakeBin,
            "#!/bin/sh\nprintf '%s' " . escapeshellarg($output) . "\nexit {$exitCode}\n"
        );
        chmod($fakeBin, 0755);

        // Prepend our fake python to PATH for this process
        $originalPath = getenv('PATH');
        putenv("PATH={$this->tempDir}:{$originalPath}");

        try {
            $result = $this->runner->run($this->tempDir);
        } finally {
            putenv("PATH={$originalPath}");
        }

        return $result;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
