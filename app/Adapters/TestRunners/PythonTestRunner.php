<?php

namespace App\Adapters\TestRunners;

use App\Contracts\TestRunner;
use App\DTOs\TestResult;
use Symfony\Component\Process\Process;

/**
 * T22 — PythonTestRunner
 *
 * Runs pytest with JSON report output (`--json-report`).
 * Falls back to text parsing if plugin unavailable.
 */
class PythonTestRunner implements TestRunner
{
    private const TIMEOUT   = 300;
    private const JSON_FILE = '/tmp/jrdev_pytest_results.json';

    public function supports(string $projectPath): bool
    {
        return file_exists($projectPath . '/requirements.txt') ||
               file_exists($projectPath . '/pyproject.toml') ||
               file_exists($projectPath . '/setup.py') ||
               file_exists($projectPath . '/pytest.ini') ||
               file_exists($projectPath . '/setup.cfg');
    }

    public function name(): string
    {
        return 'Python pytest';
    }

    public function run(string $projectPath, ?string $filter = null): TestResult
    {
        $command = $this->buildCommand($projectPath, $filter);

        $start   = microtime(true);
        $process = new Process($command, $projectPath, timeout: self::TIMEOUT);
        $process->run();
        $duration = round(microtime(true) - $start, 2);

        $output = $process->getOutput() . $process->getErrorOutput();

        return $this->parseOutput($output, $duration);
    }

    /**
     * @return string[]
     */
    private function buildCommand(string $projectPath, ?string $filter): array
    {
        $cmd = ['python', '-m', 'pytest', '--json-report', '--json-report-file=' . self::JSON_FILE, '-v'];

        if ($filter !== null) {
            $cmd[] = '-k';
            $cmd[] = $filter;
        }

        return $cmd;
    }

    private function parseOutput(string $output, float $duration): TestResult
    {
        if (file_exists(self::JSON_FILE)) {
            $data = json_decode(file_get_contents(self::JSON_FILE), true);
            @unlink(self::JSON_FILE);

            if (is_array($data)) {
                return $this->parseJsonReport($data, $output, $duration);
            }
        }

        // Fallback: parse text output
        return $this->parseTextOutput($output, $duration);
    }

    private function parseJsonReport(array $data, string $output, float $duration): TestResult
    {
        $summary = $data['summary'] ?? [];
        $passed  = $summary['passed'] ?? 0;
        $failed  = $summary['failed'] ?? 0;
        $errors  = $summary['error'] ?? 0;
        $skipped = $summary['skipped'] ?? 0;

        $failedTests = [];

        foreach ($data['tests'] ?? [] as $test) {
            if (in_array($test['outcome'], ['failed', 'error'])) {
                $failedTests[] = $test['nodeid'];
            }
        }

        $success = $failed === 0 && $errors === 0;

        if ($success) {
            return TestResult::success($passed, $skipped, $duration, $output, $this->name());
        }

        return TestResult::failure($passed, $failed, $errors, $skipped, $duration, $output, $failedTests, $this->name());
    }

    private function parseTextOutput(string $output, float $duration): TestResult
    {
        $passed  = 0;
        $failed  = 0;
        $errors  = 0;
        $skipped = 0;

        // Example: "5 passed, 2 failed, 1 error in 3.21s"
        if (preg_match('/(\d+) passed/', $output, $m)) {
            $passed = (int) $m[1];
        }

        if (preg_match('/(\d+) failed/', $output, $m)) {
            $failed = (int) $m[1];
        }

        if (preg_match('/(\d+) error/', $output, $m)) {
            $errors = (int) $m[1];
        }

        if (preg_match('/(\d+) skipped/', $output, $m)) {
            $skipped = (int) $m[1];
        }

        // Extract failed test names (FAILED path/to/test.py::test_name)
        $failedTests = [];
        preg_match_all('/FAILED\s+([^\s]+)/', $output, $matches);

        if (!empty($matches[1])) {
            $failedTests = $matches[1];
        }

        $success = $failed === 0 && $errors === 0;

        if ($success) {
            return TestResult::success($passed, $skipped, $duration, $output, $this->name());
        }

        return TestResult::failure($passed, $failed, $errors, $skipped, $duration, $output, $failedTests, $this->name());
    }
}
