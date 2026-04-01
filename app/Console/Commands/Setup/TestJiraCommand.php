<?php

namespace App\Console\Commands\Setup;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * T17 — php artisan jr:test:jira
 *
 * Fetches the current Jira user and lists accessible projects to verify credentials.
 */
class TestJiraCommand extends Command
{
    protected $signature = 'jr:test:jira
        {--url= : Jira base URL (e.g. https://yourcompany.atlassian.net)}
        {--user= : Jira account email}
        {--token= : Jira API token}';

    protected $description = 'Test Jira API connection and list accessible projects';

    public function handle(): int
    {
        $url   = $this->option('url')   ?? config('services.jira.url');
        $user  = $this->option('user')  ?? config('services.jira.username');
        $token = $this->option('token') ?? config('services.jira.api_token');

        if (!$url || !$user || !$token) {
            $this->error('Jira credentials not configured. Set JIRA_URL, JIRA_USERNAME, JIRA_API_TOKEN in .env or use options.');
            $this->line('Usage: php artisan jr:test:jira --url=https://company.atlassian.net --user=you@company.com --token=YOUR_TOKEN');

            return self::FAILURE;
        }

        $url = rtrim($url, '/');

        $this->info('Testing Jira connection...');

        // Fetch current user
        $userResponse = Http::withBasicAuth($user, $token)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("{$url}/rest/api/3/myself");

        if (!$userResponse->successful()) {
            $this->error("Jira API returned {$userResponse->status()}. Check credentials and URL.");

            return self::FAILURE;
        }

        $me = $userResponse->json();
        $this->info("✓ Authenticated as: <comment>{$me['displayName']}</comment> ({$me['emailAddress']})");

        // List projects
        $projectsResponse = Http::withBasicAuth($user, $token)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("{$url}/rest/api/3/project/search", [
                'maxResults' => 10,
                'orderBy'    => 'lastIssueUpdatedTime',
            ]);

        if (!$projectsResponse->successful()) {
            $this->warn('Could not list projects, but authentication succeeded.');

            return self::SUCCESS;
        }

        $projects = $projectsResponse->json()['values'] ?? [];

        $this->info("✓ Found " . count($projects) . " project(s) (showing up to 10):");
        $this->table(
            ['Key', 'Name', 'Type'],
            array_map(static fn (array $p) => [
                $p['key'],
                $p['name'],
                $p['projectTypeKey'] ?? 'unknown',
            ], $projects)
        );

        return self::SUCCESS;
    }
}
