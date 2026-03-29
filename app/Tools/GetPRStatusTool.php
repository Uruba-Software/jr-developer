<?php

namespace App\Tools;

use App\Contracts\ToolRunner;
use App\DTOs\ToolResult;
use App\Enums\ToolPermission;
use Illuminate\Support\Facades\Http;

/**
 * T15 — GetPRStatusTool
 *
 * Returns CI check statuses and review status for a specific pull request.
 */
class GetPRStatusTool implements ToolRunner
{
    public const NAME = 'get_pr_status';

    public function __construct(
        private readonly string $githubToken,
        private readonly string $owner,
        private readonly string $repo,
    ) {}

    public function supports(string $tool): bool
    {
        return $tool === self::NAME;
    }

    public function permission(): ToolPermission
    {
        return ToolPermission::Read;
    }

    /**
     * @param  array{number: int}  $params
     */
    public function run(string $tool, array $params): ToolResult
    {
        $number = (int) ($params['number'] ?? 0);

        if ($number <= 0) {
            return ToolResult::fail('Parameter "number" is required (PR number).');
        }

        $base = "https://api.github.com/repos/{$this->owner}/{$this->repo}";

        // Fetch PR details
        $prResponse = Http::withToken($this->githubToken)
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get("{$base}/pulls/{$number}");

        if (!$prResponse->successful()) {
            return ToolResult::fail("PR not found: #{$number}");
        }

        $pr  = $prResponse->json();
        $sha = $pr['head']['sha'];

        // Fetch check runs for the head commit
        $checksResponse = Http::withToken($this->githubToken)
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get("{$base}/commits/{$sha}/check-runs", ['per_page' => 30]);

        $checkRuns = [];

        if ($checksResponse->successful()) {
            foreach ($checksResponse->json()['check_runs'] ?? [] as $run) {
                $checkRuns[] = [
                    'name'       => $run['name'],
                    'status'     => $run['status'],
                    'conclusion' => $run['conclusion'],
                    'url'        => $run['html_url'],
                ];
            }
        }

        // Fetch reviews
        $reviewsResponse = Http::withToken($this->githubToken)
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get("{$base}/pulls/{$number}/reviews");

        $reviews = [];

        if ($reviewsResponse->successful()) {
            foreach ($reviewsResponse->json() as $review) {
                if (in_array($review['state'], ['APPROVED', 'CHANGES_REQUESTED', 'DISMISSED'])) {
                    $reviews[] = [
                        'reviewer' => $review['user']['login'] ?? null,
                        'state'    => $review['state'],
                    ];
                }
            }
        }

        return ToolResult::ok([
            'number'      => $number,
            'title'       => $pr['title'],
            'state'       => $pr['state'],
            'mergeable'   => $pr['mergeable'],
            'draft'       => $pr['draft'],
            'url'         => $pr['html_url'],
            'check_runs'  => $checkRuns,
            'reviews'     => $reviews,
            'checks_pass' => $this->allChecksPassed($checkRuns),
        ]);
    }

    /** @param  array<array{conclusion: string|null}>  $checkRuns */
    private function allChecksPassed(array $checkRuns): bool
    {
        if (empty($checkRuns)) {
            return true;
        }

        foreach ($checkRuns as $run) {
            if (!in_array($run['conclusion'], ['success', 'skipped', 'neutral', null])) {
                return false;
            }
        }

        return true;
    }
}
