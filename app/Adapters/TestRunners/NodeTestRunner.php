<?php

namespace App\Adapters\TestRunners;

use App\Contracts\TestRunner;
use App\DTOs\TestResult;
use Symfony\Component\Process\Process;

/**
 * T22 — NodeTestRunner
 *
 * Runs Jest (`npx jest --json`) or falls back to `npm test`.
 * Parses Jest JSON output for structured results.
 */
class NodeTestRunner implements TestRunner
{
    private const TIMEOUT = 300;

    public function supports(string $projectPath): bool
    {
        if (!file_exists($projectPath . '/package.json')) {
            return false;
        }

        $pkg = json_decode(file_get_contents($projectPath . '/package.json'), true) ?? [];

        // Check for jest dependency or test script
        $hasDep = isset($pkg['devDependencies']['jest']) || isset($pkg['dependencies']['jest']);
        $hasScript = str_contains($pkg['scripts']['test'] ?? '', 'jest');

        return $hasDep || $hasScript || file_exists($projectPath . '/jest.config.js') || file_exists($projectPath . '/jest.config.ts');
    }

    public function name(): string
    {
        return 'Node.js Jest';
    }

    public function run(string $projectPath, ?string $filter = null): TestResult
    {
        $command = $this->buildCommand($projectPath, $filter);

        $start   = microtime(true);
        $process = new Process($command, $projectPath, timeout: self::TIMEOUT);
        $process->run();
        $duration = round(microtime(true) - $start, 2);

        $output = $process->getOutput() . $process->getErrorOutput();

        return $this->parseJestJson($output, $duration);
    }

    /**
     * @return string[]
     */
    private function buildCommand(string $projectPath, ?string $filter): array
    {
        // Prefer local jest binary
        $jestBin = file_exists($projectPath . '/node_modules/.bin/jest')
            ? './node_modules/.bin/jest'
            : 'npx';

        $cmd = $jestBin === 'npx'
            ? ['npx', 'jest', '--json']
            : ['./node_modules/.bin/jest', '--json'];

        if ($filter !== null) {
            $cmd[] = '-t';
            $cmd[] = $filter;
        }

        return $cmd;
    }

    private function parseJestJson(string $output, float $duration): TestResult
    {
        // Jest --json outputs JSON to stdout. Find the JSON part.
        $jsonStart = strpos($output, '{');

        if ($jsonStart === false) {
            return $this->parseFallback($output, $duration);
        }

        $jsonStr = substr($output, $jsonStart);
        $data    = json_decode($jsonStr, true);

        if (!is_array($data)) {
            return $this->parseFallback($output, $duration);
        }

        $passed  = $data['numPassedTests'] ?? 0;
        $failed  = $data['numFailedTests'] ?? 0;
        $skipped = $data['numPendingTests'] ?? 0;
        $success = $data['success'] ?? false;

        $failedTests = [];

        foreach ($data['testResults'] ?? [] as $suite) {
            foreach ($suite['assertionResults'] ?? [] as $test) {
                if ($test['status'] === 'failed') {
                    $failedTests[] = $suite['testFilePath'] . ' > ' . $test['fullName'];
                }
            }
        }

        if ($success) {
            return TestResult::success($passed, $skipped, $duration, $output, $this->name());
        }

        return TestResult::failure($passed, $failed, 0, $skipped, $duration, $output, $failedTests, $this->name());
    }

    private function parseFallback(string $output, float $duration): TestResult
    {
        $passed  = 0;
        $failed  = 0;
        $skipped = 0;

        if (preg_match('/(\d+) passed/', $output, $m)) {
            $passed = (int) $m[1];
        }

        if (preg_match('/(\d+) failed/', $output, $m)) {
            $failed = (int) $m[1];
        }

        $success = $failed === 0;

        if ($success) {
            return TestResult::success($passed, $skipped, $duration, $output, $this->name());
        }

        return TestResult::failure($passed, $failed, 0, $skipped, $duration, $output, [], $this->name());
    }
}
