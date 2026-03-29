<?php

namespace App\Console\Commands\Setup;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * T17 — php artisan jr:test:github
 *
 * Lists repositories accessible with the configured GitHub token and confirms access.
 */
class TestGitHubCommand extends Command
{
    protected $signature   = 'jr:test:github {--token= : GitHub personal access token (overrides config)}';
    protected $description = 'Test GitHub API connection and list accessible repositories';

    public function handle(): int
    {
        $token = $this->option('token') ?? config('services.github.token');

        if (!$token) {
            $this->error('No GitHub token configured. Set GITHUB_TOKEN in .env or use --token option.');

            return self::FAILURE;
        }

        $this->info('Testing GitHub connection...');

        // Test user identity
        $userResponse = Http::withToken($token)
            ->withHeaders([
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'jr-developer/1.0',
            ])
            ->get('https://api.github.com/user');

        if (!$userResponse->successful()) {
            $this->error("GitHub API returned {$userResponse->status()}: " . $userResponse->json('message', 'Unknown error'));

            return self::FAILURE;
        }

        $user = $userResponse->json();
        $this->info("✓ Authenticated as: <comment>{$user['login']}</comment> ({$user['name']})");

        // List repositories
        $reposResponse = Http::withToken($token)
            ->withHeaders([
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'jr-developer/1.0',
            ])
            ->get('https://api.github.com/user/repos', [
                'per_page' => 10,
                'sort'     => 'updated',
                'type'     => 'owner',
            ]);

        if (!$reposResponse->successful()) {
            $this->warn('Could not list repositories, but authentication succeeded.');

            return self::SUCCESS;
        }

        $repos = $reposResponse->json();

        $this->info("✓ Found " . count($repos) . " repositories (showing 10 most recent):");
        $this->table(
            ['Repository', 'Default branch', 'Private', 'Last pushed'],
            array_map(static fn (array $r) => [
                $r['full_name'],
                $r['default_branch'],
                $r['private'] ? 'Yes' : 'No',
                substr($r['pushed_at'] ?? 'unknown', 0, 10),
            ], $repos)
        );

        return self::SUCCESS;
    }
}
