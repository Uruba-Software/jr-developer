<?php

namespace Tests\Unit\Services;

use App\DTOs\TestResult;
use App\Services\TestResultFormatter;
use Tests\TestCase;

class TestResultFormatterTest extends TestCase
{
    private TestResultFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new TestResultFormatter();
    }

    // ── format() — success ────────────────────────────────────────────────────

    public function test_format_success_returns_summary_only(): void
    {
        $result = TestResult::success(
            passed: 10, skipped: 0, duration: 1.5,
            output: 'OK', runner: 'Laravel PHPUnit',
        );

        $formatted = $this->formatter->format($result);

        $this->assertArrayHasKey('summary', $formatted);
        $this->assertArrayHasKey('detail', $formatted);
        $this->assertArrayHasKey('needs_retry_prompt', $formatted);

        $this->assertNull($formatted['detail']);
        $this->assertFalse($formatted['needs_retry_prompt']);
        $this->assertStringContainsString('10 passed', $formatted['summary']);
    }

    // ── format() — failure ────────────────────────────────────────────────────

    public function test_format_failure_includes_detail_and_retry_prompt(): void
    {
        $result = TestResult::failure(
            passed: 3, failed: 2, errors: 0, skipped: 0,
            duration: 2.0, output: 'Some output',
            failedTests: ['FooTest::test_a', 'FooTest::test_b'],
            runner: 'Laravel PHPUnit',
        );

        $formatted = $this->formatter->format($result);

        $this->assertNotNull($formatted['detail']);
        $this->assertTrue($formatted['needs_retry_prompt']);
        $this->assertStringContainsString('FooTest::test_a', $formatted['detail']);
        $this->assertStringContainsString('FooTest::test_b', $formatted['detail']);
    }

    public function test_format_failure_with_no_failed_test_names(): void
    {
        $result = TestResult::failure(
            passed: 0, failed: 1, errors: 0, skipped: 0,
            duration: 0.5, output: 'Error: something went wrong',
            failedTests: [], runner: 'Node.js Jest',
        );

        $formatted = $this->formatter->format($result);

        $this->assertTrue($formatted['needs_retry_prompt']);
        $this->assertStringContainsString('Error: something went wrong', $formatted['detail']);
    }

    // ── format() — detail listing limits ──────────────────────────────────────

    public function test_format_limits_listed_failures_to_ten(): void
    {
        $failedTests = array_map(fn($i) => "TestClass::test_{$i}", range(1, 15));

        $result = TestResult::failure(
            passed: 0, failed: 15, errors: 0, skipped: 0,
            duration: 3.0, output: 'many failures',
            failedTests: $failedTests, runner: 'Laravel PHPUnit',
        );

        $formatted = $this->formatter->format($result);

        $this->assertStringContainsString('5 more', $formatted['detail']);
        // Only first 10 listed individually
        $this->assertStringContainsString('TestClass::test_10', $formatted['detail']);
        $this->assertStringNotContainsString('TestClass::test_11', $formatted['detail']);
    }

    // ── toMessage() ───────────────────────────────────────────────────────────

    public function test_to_message_success_contains_summary_only(): void
    {
        $result = TestResult::success(
            passed: 5, skipped: 1, duration: 0.9,
            output: '', runner: 'Python pytest',
        );

        $message = $this->formatter->toMessage($result);

        $this->assertStringContainsString('5 passed', $message);
        $this->assertStringNotContainsString('Should I try to fix', $message);
    }

    public function test_to_message_failure_contains_retry_prompt(): void
    {
        $result = TestResult::failure(
            passed: 1, failed: 1, errors: 0, skipped: 0,
            duration: 1.0, output: 'FAILED test_x',
            failedTests: ['test_x'], runner: 'Python pytest',
        );

        $message = $this->formatter->toMessage($result);

        $this->assertStringContainsString('Should I try to fix the failing tests?', $message);
    }

    public function test_to_message_failure_contains_detail_and_summary(): void
    {
        $result = TestResult::failure(
            passed: 2, failed: 1, errors: 0, skipped: 0,
            duration: 1.5, output: 'FAILED FooTest',
            failedTests: ['FooTest::bad_test'], runner: 'Laravel PHPUnit',
        );

        $message = $this->formatter->toMessage($result);

        $this->assertStringContainsString('2 passed', $message);
        $this->assertStringContainsString('FooTest::bad_test', $message);
    }

    // ── buildAiRetryContext() ──────────────────────────────────────────────────

    public function test_build_ai_retry_context_contains_runner_and_counts(): void
    {
        $result = TestResult::failure(
            passed: 4, failed: 2, errors: 1, skipped: 0,
            duration: 2.0, output: 'FAILURES\ntest_a failed\ntest_b failed',
            failedTests: ['ModuleTest::test_a', 'ModuleTest::test_b'],
            runner: 'Laravel PHPUnit',
        );

        $context = $this->formatter->buildAiRetryContext($result);

        $this->assertStringContainsString('Laravel PHPUnit', $context);
        $this->assertStringContainsString('2 failed', $context);
        $this->assertStringContainsString('1 errors', $context);
        $this->assertStringContainsString('4 passed', $context);
        $this->assertStringContainsString('ModuleTest::test_a', $context);
        $this->assertStringContainsString('ModuleTest::test_b', $context);
    }

    public function test_build_ai_retry_context_limits_failing_tests_listed(): void
    {
        $failedTests = array_map(fn($i) => "Mod::test_{$i}", range(1, 15));

        $result = TestResult::failure(
            passed: 0, failed: 15, errors: 0, skipped: 0,
            duration: 5.0, output: 'many failures',
            failedTests: $failedTests, runner: 'Node.js Jest',
        );

        $context = $this->formatter->buildAiRetryContext($result);

        $this->assertStringContainsString('5 more', $context);
    }

    public function test_build_ai_retry_context_includes_output_block(): void
    {
        $result = TestResult::failure(
            passed: 0, failed: 1, errors: 0, skipped: 0,
            duration: 0.5, output: 'ERROR: connection refused',
            failedTests: [], runner: 'Python pytest',
        );

        $context = $this->formatter->buildAiRetryContext($result);

        $this->assertStringContainsString('```', $context);
        $this->assertStringContainsString('ERROR: connection refused', $context);
    }

    // ── extractRelevantOutput truncation ──────────────────────────────────────

    public function test_long_output_is_truncated_to_max_chars(): void
    {
        $longOutput = str_repeat('x', 3000);

        $result = TestResult::failure(
            passed: 0, failed: 1, errors: 0, skipped: 0,
            duration: 1.0, output: $longOutput,
            failedTests: [], runner: 'Laravel PHPUnit',
        );

        $message = $this->formatter->toMessage($result);

        // The message must mention truncation
        $this->assertStringContainsString('truncated', $message);
    }

    public function test_long_output_with_failures_marker_is_extracted_from_marker(): void
    {
        // Pad before the FAILURES marker so total output > 2000 chars
        $prefix  = str_repeat('a', 2500);
        $suffix  = "FAILURES\ntest_one failed badly\n";
        $output  = $prefix . $suffix;

        $result = TestResult::failure(
            passed: 0, failed: 1, errors: 0, skipped: 0,
            duration: 1.0, output: $output,
            failedTests: [], runner: 'Laravel PHPUnit',
        );

        $message = $this->formatter->toMessage($result);

        $this->assertStringContainsString('FAILURES', $message);
        $this->assertStringContainsString('truncated', $message);
    }
}
