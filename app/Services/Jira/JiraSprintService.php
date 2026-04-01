<?php

namespace App\Services\Jira;

use App\DTOs\JiraIssue;
use App\DTOs\JiraSprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * T20 — JiraSprintService
 *
 * Reads sprint and board data from the Jira Agile (Software) API.
 * Uses the same 5-minute Redis cache strategy as JiraService.
 *
 * Note: Board/sprint APIs require the "Software" license tier.
 */
class JiraSprintService
{
    private const CACHE_TTL = 300;

    private string $baseUrl;

    public function __construct(
        string $baseUrl,
        private readonly string $username,
        private readonly string $apiToken,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    // -------------------------------------------------------------------------
    // Sprint operations
    // -------------------------------------------------------------------------

    /**
     * Get the active sprint for a board.
     */
    public function getActiveSprint(int $boardId): ?JiraSprint
    {
        $cacheKey = $this->cacheKey("board:{$boardId}:active-sprint");

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($boardId): ?JiraSprint {
            $response = $this->get("/rest/agile/1.0/board/{$boardId}/sprint", [
                'state' => 'active',
            ]);

            if (!$response) {
                return null;
            }

            $sprints = $response['values'] ?? [];

            if (empty($sprints)) {
                return null;
            }

            return JiraSprint::fromApiResponse($sprints[0]);
        });
    }

    /**
     * Get all issues in a sprint.
     *
     * @return JiraIssue[]
     */
    public function getSprintIssues(int $sprintId): array
    {
        $cacheKey = $this->cacheKey("sprint:{$sprintId}:issues");

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($sprintId): array {
            $response = $this->get("/rest/agile/1.0/sprint/{$sprintId}/issue", [
                'fields' => 'summary,status,assignee,reporter,priority,issuetype,labels,duedate,customfield_10016,customfield_10020',
            ]);

            if (!$response) {
                return [];
            }

            return array_map(
                fn (array $issue) => JiraIssue::fromApiResponse($issue, $this->baseUrl),
                $response['issues'] ?? []
            );
        });
    }

    /**
     * Get all issues assigned to a specific Jira account ID.
     * Uses JQL search filtered by assignee.
     *
     * @return JiraIssue[]
     */
    public function getIssuesByAssignee(string $accountId, ?int $sprintId = null): array
    {
        $jql = "assignee = \"{$accountId}\" AND resolution = Unresolved";

        if ($sprintId !== null) {
            $jql .= " AND sprint = {$sprintId}";
        }

        $cacheKey = $this->cacheKey("assignee:{$accountId}:sprint:{$sprintId}:issues");

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($jql): array {
            $response = $this->get('/rest/api/3/search', [
                'jql'        => $jql,
                'maxResults' => 50,
                'fields'     => 'summary,status,assignee,reporter,priority,issuetype,labels,duedate,customfield_10016,customfield_10020',
            ]);

            if (!$response) {
                return [];
            }

            return array_map(
                fn (array $issue) => JiraIssue::fromApiResponse($issue, $this->baseUrl),
                $response['issues'] ?? []
            );
        });
    }

    /**
     * Get all sprints for a board (active, future, closed).
     *
     * @return JiraSprint[]
     */
    public function getAllSprints(int $boardId, string $state = 'active,future'): array
    {
        $cacheKey = $this->cacheKey("board:{$boardId}:sprints:{$state}");

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($boardId, $state): array {
            $response = $this->get("/rest/agile/1.0/board/{$boardId}/sprint", [
                'state' => $state,
            ]);

            if (!$response) {
                return [];
            }

            return array_map(
                fn (array $sprint) => JiraSprint::fromApiResponse($sprint),
                $response['values'] ?? []
            );
        });
    }

    // -------------------------------------------------------------------------
    // HTTP helpers
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private function get(string $path, array $query = []): ?array
    {
        $response = Http::withBasicAuth($this->username, $this->apiToken)
            ->withHeaders(['Accept' => 'application/json'])
            ->get($this->baseUrl . $path, $query);

        if (!$response->successful()) {
            Log::warning("JiraSprintService GET {$path} failed: {$response->status()}", [
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json();
    }

    private function cacheKey(string $suffix): string
    {
        $host = parse_url($this->baseUrl, PHP_URL_HOST) ?? 'jira';

        return "jira:{$host}:{$suffix}";
    }
}
