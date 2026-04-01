<?php

namespace App\Console\Commands\Setup;

use Illuminate\Console\Command;

/**
 * T17 — php artisan jr:test:all
 *
 * Runs all connection tests (GitHub, Slack, Jira) and shows a summary table.
 */
class TestAllCommand extends Command
{
    protected $signature   = 'jr:test:all';
    protected $description = 'Run all connection tests and display a summary';

    public function handle(): int
    {
        $this->info('Running all connection tests...');
        $this->newLine();

        $tests   = [];
        $allPass = true;

        // GitHub
        $githubResult = $this->runTest('jr:test:github');
        $tests[]      = ['GitHub', $githubResult ? '✓ OK' : '✗ Failed', $githubResult ? 'success' : 'error'];
        $allPass      = $allPass && $githubResult;

        // Slack
        $slackResult = $this->runTest('jr:test:slack');
        $tests[]     = ['Slack', $slackResult ? '✓ OK' : '✗ Failed', $slackResult ? 'success' : 'error'];
        $allPass     = $allPass && $slackResult;

        // Jira
        $jiraResult = $this->runTest('jr:test:jira');
        $tests[]    = ['Jira', $jiraResult ? '✓ OK' : '✗ Failed', $jiraResult ? 'success' : 'error'];
        $allPass    = $allPass && $jiraResult;

        $this->newLine();
        $this->info('=== Connection Test Summary ===');
        $this->table(
            ['Service', 'Status'],
            array_map(static fn (array $t) => [$t[0], $t[1]], $tests)
        );

        if ($allPass) {
            $this->info('All connection tests passed.');

            return self::SUCCESS;
        }

        $failed = array_filter($tests, static fn (array $t) => $t[2] === 'error');
        $this->warn(count($failed) . ' test(s) failed. Check credentials in .env or project config.');

        return self::FAILURE;
    }

    private function runTest(string $command): bool
    {
        $this->line("Running: <comment>{$command}</comment>");
        $exitCode = $this->call($command);

        return $exitCode === self::SUCCESS;
    }
}
