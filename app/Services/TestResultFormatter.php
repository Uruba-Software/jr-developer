<?php

namespace App\Services;

use App\DTOs\TestResult;

/**
 * T23 — TestResultFormatter
 *
 * Formats test results into messages for Slack/Discord delivery.
 * Produces:
 *   - A short summary line (e.g. "✅ 42 passed | ❌ 2 failed")
 *   - A detailed block listing failing test names with error excerpts
 *   - A retry prompt when tests fail
 */
class TestResultFormatter
{
    /**
     * Max characters of output to include in failure details.
     * Prevents huge messages from drowning chat.
     */
    private const MAX_FAILURE_CHARS = 2000;

    /**
     * Max number of failing tests to list individually.
     */
    private const MAX_FAILURES_LISTED = 10;

    /**
     * Format a TestResult for a messaging platform.
     *
     * @return array{summary: string, detail: string|null, needs_retry_prompt: bool}
     */
    public function format(TestResult $result): array
    {
        $summary = $result->summary();

        if ($result->success) {
            return [
                'summary'             => $summary,
                'detail'              => null,
                'needs_retry_prompt'  => false,
            ];
        }

        $detail = $this->buildFailureDetail($result);

        return [
            'summary'             => $summary,
            'detail'              => $detail,
            'needs_retry_prompt'  => true,
        ];
    }

    /**
     * Build the full Slack/Discord message for a test run.
     * Returns a single string ready to send.
     */
    public function toMessage(TestResult $result): string
    {
        $formatted = $this->format($result);
        $message   = $formatted['summary'];

        if ($formatted['detail'] !== null) {
            $message .= "\n\n" . $formatted['detail'];
        }

        if ($formatted['needs_retry_prompt']) {
            $message .= "\n\n_Should I try to fix the failing tests?_";
        }

        return $message;
    }

    /**
     * Build context to inject into AI when retrying after failure.
     * This gives the AI enough information to diagnose and fix the failures.
     */
    public function buildAiRetryContext(TestResult $result): string
    {
        $lines = [
            "## Test Failure Report",
            "",
            "Runner: {$result->runner}",
            "Result: {$result->failed} failed, {$result->errors} errors, {$result->passed} passed",
            "",
        ];

        if (!empty($result->failedTests)) {
            $lines[] = "### Failing Tests";

            foreach (array_slice($result->failedTests, 0, self::MAX_FAILURES_LISTED) as $test) {
                $lines[] = "- {$test}";
            }

            if (count($result->failedTests) > self::MAX_FAILURES_LISTED) {
                $remaining = count($result->failedTests) - self::MAX_FAILURES_LISTED;
                $lines[]   = "- ... and {$remaining} more";
            }

            $lines[] = "";
        }

        $lines[] = "### Output";
        $output  = $this->extractRelevantOutput($result->output);
        $lines[] = "```\n{$output}\n```";

        return implode("\n", $lines);
    }

    private function buildFailureDetail(TestResult $result): string
    {
        $lines = [];

        if (!empty($result->failedTests)) {
            $listed  = array_slice($result->failedTests, 0, self::MAX_FAILURES_LISTED);
            $lines[] = "**Failing tests:**";

            foreach ($listed as $test) {
                $lines[] = "• `{$test}`";
            }

            $remaining = count($result->failedTests) - count($listed);

            if ($remaining > 0) {
                $lines[] = "• _{$remaining} more..._";
            }

            $lines[] = '';
        }

        // Include truncated output
        $output = $this->extractRelevantOutput($result->output);

        if ($output !== '') {
            $lines[] = "```\n{$output}\n```";
        }

        return implode("\n", $lines);
    }

    /**
     * Extract the most relevant part of the output.
     * Prefers the error section over the full output.
     */
    private function extractRelevantOutput(string $output): string
    {
        if (strlen($output) <= self::MAX_FAILURE_CHARS) {
            return $output;
        }

        // Try to find the failures section
        $markers = ['FAILURES', 'ERRORS', '× ', 'FAILED', '--- FAIL'];

        foreach ($markers as $marker) {
            $pos = strrpos($output, $marker);

            if ($pos !== false) {
                $excerpt = substr($output, $pos, self::MAX_FAILURE_CHARS);

                return $excerpt . "\n... (truncated)";
            }
        }

        // Fall back to the last N characters
        return '... (truncated) ...' . substr($output, -self::MAX_FAILURE_CHARS);
    }
}
