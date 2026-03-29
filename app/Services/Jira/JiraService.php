<?php

namespace App\Services\Jira;

use App\DTOs\JiraIssue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * T19 — JiraService
 *
 * Wraps the Jira REST API v3. All GET responses are cached in Redis
 * with a 5-minute TTL to reduce API calls.
 *
 * Credentials are injected per instance so each project can use its own.
 */
class JiraService
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
    // Issue operations
    // -------------------------------------------------------------------------

    /**
     * Get a single Jira issue by key (e.g. "EK-123").
     * Result is cached for 5 minutes.
     */
    public function getIssue(string $key): ?JiraIssue
    {
        $cacheKey = $this->cacheKey("issue:{$key}");

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key): ?JiraIssue {
            $response = $this->get("/rest/api/3/issue/{$key}");

            if (!$response) {
                return null;
            }

            return JiraIssue::fromApiResponse($response, $this->baseUrl);
        });
    }

    /**
     * Add a comment to an issue.
     */
    public function addComment(string $key, string $text): bool
    {
        $response = $this->post("/rest/api/3/issue/{$key}/comment", [
            'body' => [
                'type'    => 'doc',
                'version' => 1,
                'content' => [
                    [
                        'type'    => 'paragraph',
                        'content' => [['type' => 'text', 'text' => $text]],
                    ],
                ],
            ],
        ]);

        if ($response) {
            $this->forgetIssueCache($key);
        }

        return (bool) $response;
    }

    /**
     * Transition an issue to a new status by transition ID or name.
     */
    public function updateStatus(string $key, string $transitionIdOrName): bool
    {
        // Resolve transition name to ID if needed
        $transitionId = is_numeric($transitionIdOrName)
            ? $transitionIdOrName
            : $this->resolveTransitionId($key, $transitionIdOrName);

        if ($transitionId === null) {
            Log::warning("JiraService: transition not found for '{$transitionIdOrName}' on issue {$key}");

            return false;
        }

        $response = $this->post("/rest/api/3/issue/{$key}/transitions", [
            'transition' => ['id' => $transitionId],
        ]);

        if ($response !== null) {
            $this->forgetIssueCache($key);

            return true;
        }

        return false;
    }

    /**
     * Search issues using JQL.
     *
     * @return JiraIssue[]
     */
    public function searchIssues(string $jql, int $maxResults = 50): array
    {
        $cacheKey = $this->cacheKey('search:' . md5($jql . $maxResults));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($jql, $maxResults): array {
            $response = $this->get('/rest/api/3/search', [
                'jql'        => $jql,
                'maxResults' => $maxResults,
                'fields'     => 'summary,status,assignee,reporter,priority,issuetype,labels,duedate,story_points,customfield_10016,customfield_10020',
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

    // -------------------------------------------------------------------------
    // Transition helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<array{id: string, name: string}>
     */
    public function getTransitions(string $key): array
    {
        $response = $this->get("/rest/api/3/issue/{$key}/transitions");

        return $response['transitions'] ?? [];
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
            Log::warning("JiraService GET {$path} failed: {$response->status()}", [
                'body' => $response->body(),
            ]);

            return null;
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function post(string $path, array $data = []): ?array
    {
        $response = Http::withBasicAuth($this->username, $this->apiToken)
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->post($this->baseUrl . $path, $data);

        if (!$response->successful()) {
            Log::warning("JiraService POST {$path} failed: {$response->status()}", [
                'body' => $response->body(),
            ]);

            return null;
        }

        // Some endpoints return 204 with no body
        $body = $response->body();

        return $body !== '' ? ($response->json() ?? []) : [];
    }

    private function cacheKey(string $suffix): string
    {
        $host = parse_url($this->baseUrl, PHP_URL_HOST) ?? 'jira';

        return "jira:{$host}:{$suffix}";
    }

    private function forgetIssueCache(string $key): void
    {
        Cache::forget($this->cacheKey("issue:{$key}"));
    }

    private function resolveTransitionId(string $issueKey, string $name): ?string
    {
        $transitions = $this->getTransitions($issueKey);

        foreach ($transitions as $transition) {
            if (strtolower($transition['name']) === strtolower($name)) {
                return $transition['id'];
            }
        }

        return null;
    }
}
