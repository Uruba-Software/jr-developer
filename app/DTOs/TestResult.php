<?php

namespace App\DTOs;

/**
 * T21 — TestResult DTO
 *
 * Represents the outcome of running a test suite.
 */
readonly class TestResult
{
    /**
     * @param  int      $passed    Number of passing tests
     * @param  int      $failed    Number of failing tests
     * @param  int      $errors    Number of errored tests (setup/teardown errors)
     * @param  int      $skipped   Number of skipped tests
     * @param  string   $output    Raw or formatted output from the test runner
     * @param  float    $duration  Execution time in seconds
     * @param  bool     $success   Whether all tests passed
     * @param  string[] $failedTests  Names of failing test methods
     * @param  string   $runner    Name of the adapter that ran the tests
     */
    public function __construct(
        public int    $passed,
        public int    $failed,
        public int    $errors,
        public int    $skipped,
        public string $output,
        public float  $duration,
        public bool   $success,
        public array  $failedTests,
        public string $runner,
    ) {}

    public static function success(
        int    $passed,
        int    $skipped,
        float  $duration,
        string $output,
        string $runner,
    ): self {
        return new self(
            passed:      $passed,
            failed:      0,
            errors:      0,
            skipped:     $skipped,
            output:      $output,
            duration:    $duration,
            success:     true,
            failedTests: [],
            runner:      $runner,
        );
    }

    public static function failure(
        int    $passed,
        int    $failed,
        int    $errors,
        int    $skipped,
        float  $duration,
        string $output,
        array  $failedTests,
        string $runner,
    ): self {
        return new self(
            passed:      $passed,
            failed:      $failed,
            errors:      $errors,
            skipped:     $skipped,
            output:      $output,
            duration:    $duration,
            success:     false,
            failedTests: $failedTests,
            runner:      $runner,
        );
    }

    public function summary(): string
    {
        $icon = $this->success ? '✅' : '❌';
        $line = "{$icon} {$this->runner}: {$this->passed} passed";

        if ($this->failed > 0) {
            $line .= ", {$this->failed} failed";
        }

        if ($this->errors > 0) {
            $line .= ", {$this->errors} errors";
        }

        if ($this->skipped > 0) {
            $line .= ", {$this->skipped} skipped";
        }

        $line .= " ({$this->duration}s)";

        return $line;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'passed'       => $this->passed,
            'failed'       => $this->failed,
            'errors'       => $this->errors,
            'skipped'      => $this->skipped,
            'output'       => $this->output,
            'duration'     => $this->duration,
            'success'      => $this->success,
            'failed_tests' => $this->failedTests,
            'runner'       => $this->runner,
            'summary'      => $this->summary(),
        ];
    }
}
