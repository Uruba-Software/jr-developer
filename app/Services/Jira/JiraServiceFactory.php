<?php

namespace App\Services\Jira;

use App\Models\Project;
use InvalidArgumentException;

/**
 * Factory that creates JiraService / JiraSprintService instances
 * using per-project credentials (stored in projects.config).
 *
 * Falls back to environment variables for single-project setups.
 */
class JiraServiceFactory
{
    public function forProject(?Project $project = null): JiraService
    {
        [$url, $username, $token] = $this->resolveCredentials($project);

        return new JiraService($url, $username, $token);
    }

    public function sprintForProject(?Project $project = null): JiraSprintService
    {
        [$url, $username, $token] = $this->resolveCredentials($project);

        return new JiraSprintService($url, $username, $token);
    }

    /**
     * @return array{string, string, string}  [url, username, token]
     */
    private function resolveCredentials(?Project $project): array
    {
        $config = $project?->config ?? [];

        $url      = $config['jira']['url']      ?? config('services.jira.url');
        $username = $config['jira']['username']  ?? config('services.jira.username');
        $token    = $config['jira']['api_token'] ?? config('services.jira.api_token');

        if (!$url || !$username || !$token) {
            throw new InvalidArgumentException(
                'Jira credentials not configured. Set JIRA_URL, JIRA_USERNAME, JIRA_API_TOKEN in .env or project config.'
            );
        }

        return [$url, $username, $token];
    }
}
