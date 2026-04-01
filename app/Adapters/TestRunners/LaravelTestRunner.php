<?php

namespace App\Adapters\TestRunners;

use App\Contracts\TestRunner;
use App\DTOs\TestResult;
use Symfony\Component\Process\Process;

/**
 * T21 — LaravelTestRunner
 *
 * Runs `php artisan test` (or `./vendor/bin/phpunit`) and parses
 * the JUnit XML output to produce a structured TestResult.
 */
class LaravelTestRunner implements TestRunner
{
    private const JUNIT_FILE = '/tmp/jrdev_phpunit_results.xml';

    private const TIMEOUT = 300; // 5 minutes

    public function supports(string $projectPath): bool
    {
        return file_exists($projectPath . '/artisan') ||
               file_exists($projectPath . '/vendor/bin/phpunit');
    }

    public function name(): string
    {
        return 'Laravel PHPUnit';
    }

    public function run(string $projectPath, ?string $filter = null): TestResult
    {
        $command = $this->buildCommand($projectPath, $filter);

        $start   = microtime(true);
        $process = new Process($command, $projectPath, timeout: self::TIMEOUT);
        $process->run();
        $duration = round(microtime(true) - $start, 2);

        $output = $process->getOutput() . $process->getErrorOutput();

        return $this->parseJUnitXml($output, $duration);
    }

    /**
     * @return string[]
     */
    private function buildCommand(string $projectPath, ?string $filter): array
    {
        $junitFlag = '--log-junit ' . self::JUNIT_FILE;

        if (file_exists($projectPath . '/artisan')) {
            $cmd = ['php', 'artisan', 'test', '--log-junit', self::JUNIT_FILE];
        } else {
            $cmd = ['./vendor/bin/phpunit', '--log-junit', self::JUNIT_FILE];
        }

        if ($filter !== null) {
            $cmd[] = '--filter';
            $cmd[] = $filter;
        }

        return $cmd;
    }

    private function parseJUnitXml(string $output, float $duration): TestResult
    {
        if (!file_exists(self::JUNIT_FILE)) {
            return $this->parseTextOutput($output, $duration);
        }

        $xml = @simplexml_load_file(self::JUNIT_FILE);
        @unlink(self::JUNIT_FILE);

        if (!$xml) {
            return $this->parseTextOutput($output, $duration);
        }

        $passed  = 0;
        $failed  = 0;
        $errors  = 0;
        $skipped = 0;
        $failedTests = [];

        foreach ($xml->testsuite as $suite) {
            foreach ($suite->testcase as $testcase) {
                if (isset($testcase->failure) || isset($testcase->error)) {
                    $failed++;
                    $failedTests[] = (string) ($testcase['classname'] . '::' . $testcase['name']);

                    if (isset($testcase->error)) {
                        $errors++;
                    }
                } elseif (isset($testcase->skipped)) {
                    $skipped++;
                } else {
                    $passed++;
                }
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
        // Fallback: parse text output (e.g. "Tests: 5 passed, 2 failed")
        $passed  = 0;
        $failed  = 0;
        $errors  = 0;
        $skipped = 0;

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

        // Extract failed test names
        $failedTests = [];

        preg_match_all('/FAILED\s+(.+?)\s*$/m', $output, $matches);

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
