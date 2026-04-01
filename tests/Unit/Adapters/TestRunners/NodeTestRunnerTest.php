<?php

namespace Tests\Unit\Adapters\TestRunners;

use App\Adapters\TestRunners\NodeTestRunner;
use App\DTOs\TestResult;
use Tests\TestCase;

class NodeTestRunnerTest extends TestCase
{
    private NodeTestRunner $runner;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner  = new NodeTestRunner();
        $this->tempDir = sys_get_temp_dir() . '/jrdev_node_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDir($this->tempDir);
    }

    // ── supports() ────────────────────────────────────────────────────────────

    public function test_supports_project_with_jest_dependency(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'devDependencies' => ['jest' => '^29.0.0'],
        ]));

        $this->assertTrue($this->runner->supports($this->tempDir));
    }

    public function test_supports_project_with_jest_in_scripts(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'scripts' => ['test' => 'jest --coverage'],
        ]));

        $this->assertTrue($this->runner->supports($this->tempDir));
    }

    public function test_supports_project_with_jest_config_js(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([]));
        touch($this->tempDir . '/jest.config.js');

        $this->assertTrue($this->runner->supports($this->tempDir));
    }

    public function test_supports_project_with_jest_config_ts(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([]));
        touch($this->tempDir . '/jest.config.ts');

        $this->assertTrue($this->runner->supports($this->tempDir));
    }

    public function test_does_not_support_project_without_package_json(): void
    {
        $this->assertFalse($this->runner->supports($this->tempDir));
    }

    public function test_does_not_support_project_with_package_json_but_no_jest(): void
    {
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'devDependencies' => ['mocha' => '^10.0.0'],
            'scripts'         => ['test' => 'mocha'],
        ]));

        $this->assertFalse($this->runner->supports($this->tempDir));
    }

    // ── name() ────────────────────────────────────────────────────────────────

    public function test_name_returns_expected_string(): void
    {
        $this->assertSame('Node.js Jest', $this->runner->name());
    }

    // ── parseJestJson ─────────────────────────────────────────────────────────

    public function test_parses_jest_json_success(): void
    {
        $jestJson = json_encode([
            'success'          => true,
            'numPassedTests'   => 5,
            'numFailedTests'   => 0,
            'numPendingTests'  => 1,
            'testResults'      => [],
        ]);

        $result = $this->runWithFakeJest($jestJson, exitCode: 0);

        $this->assertInstanceOf(TestResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame(5, $result->passed);
        $this->assertSame(0, $result->failed);
        $this->assertSame(1, $result->skipped);
        $this->assertSame('Node.js Jest', $result->runner);
    }

    public function test_parses_jest_json_with_failures(): void
    {
        $jestJson = json_encode([
            'success'         => false,
            'numPassedTests'  => 3,
            'numFailedTests'  => 2,
            'numPendingTests' => 0,
            'testResults'     => [
                [
                    'testFilePath'     => '/project/src/foo.test.js',
                    'assertionResults' => [
                        ['status' => 'passed', 'fullName' => 'passes fine'],
                        ['status' => 'failed', 'fullName' => 'fails here'],
                    ],
                ],
                [
                    'testFilePath'     => '/project/src/bar.test.js',
                    'assertionResults' => [
                        ['status' => 'failed', 'fullName' => 'also fails'],
                    ],
                ],
            ],
        ]);

        $result = $this->runWithFakeJest($jestJson, exitCode: 1);

        $this->assertFalse($result->success);
        $this->assertSame(3, $result->passed);
        $this->assertSame(2, $result->failed);
        $this->assertCount(2, $result->failedTests);
        $this->assertStringContainsString('fails here', $result->failedTests[0]);
        $this->assertStringContainsString('also fails', $result->failedTests[1]);
    }

    public function test_falls_back_when_no_json_in_output(): void
    {
        $result = $this->runWithFakeJest('No JSON here at all!', exitCode: 1);

        $this->assertInstanceOf(TestResult::class, $result);
        $this->assertSame('Node.js Jest', $result->runner);
    }

    public function test_fallback_parses_text_with_passed_and_failed(): void
    {
        $result = $this->runWithFakeJest("Tests: 4 passed, 2 failed, 6 total\n", exitCode: 1);

        $this->assertFalse($result->success);
        $this->assertSame(4, $result->passed);
        $this->assertSame(2, $result->failed);
    }

    public function test_fallback_parses_all_pass_text(): void
    {
        $result = $this->runWithFakeJest("Tests: 6 passed, 6 total\n", exitCode: 0);

        $this->assertTrue($result->success);
        $this->assertSame(6, $result->passed);
        $this->assertSame(0, $result->failed);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Create a fake jest binary that outputs the given payload and run the runner.
     */
    private function runWithFakeJest(string $output, int $exitCode): TestResult
    {
        $binDir = $this->tempDir . '/node_modules/.bin';
        mkdir($binDir, 0755, true);

        $jestBin = $binDir . '/jest';
        $escaped = addslashes($output);
        file_put_contents($jestBin, "#!/bin/sh\nprintf '%s' " . escapeshellarg($output) . "\nexit {$exitCode}\n");
        chmod($jestBin, 0755);

        // Write a minimal package.json so supports() would return true
        file_put_contents($this->tempDir . '/package.json', json_encode([
            'devDependencies' => ['jest' => '^29.0.0'],
        ]));

        return $this->runner->run($this->tempDir);
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
