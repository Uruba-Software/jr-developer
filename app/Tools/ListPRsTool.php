<?php

namespace App\Tools;

use App\Contracts\ToolRunner;
use App\DTOs\ToolResult;
use App\Enums\ToolPermission;
use Illuminate\Support\Facades\Http;

/**
 * T15 — ListPRsTool
 *
 * Lists open pull requests for the configured GitHub repository.
 */
class ListPRsTool implements ToolRunner
{
    public const NAME = 'list_prs';

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
     * @param  array{state?: string, limit?: int}  $params
     */
    public function run(string $tool, array $params): ToolResult
    {
        $state = $params['state'] ?? 'open';
        $limit = min((int) ($params['limit'] ?? 20), 50);

        $response = Http::withToken($this->githubToken)
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get("https://api.github.com/repos/{$this->owner}/{$this->repo}/pulls", [
                'state'    => $state,
                'per_page' => $limit,
            ]);

        if (!$response->successful()) {
            return ToolResult::fail("GitHub API error: {$response->status()} — {$response->body()}");
        }

        $prs = array_map(static function (array $pr): array {
            return [
                'number'    => $pr['number'],
                'title'     => $pr['title'],
                'state'     => $pr['state'],
                'author'    => $pr['user']['login'] ?? null,
                'branch'    => $pr['head']['ref'] ?? null,
                'base'      => $pr['base']['ref'] ?? null,
                'draft'     => $pr['draft'] ?? false,
                'url'       => $pr['html_url'],
                'created_at' => $pr['created_at'],
            ];
        }, $response->json());

        return ToolResult::ok([
            'repo'  => "{$this->owner}/{$this->repo}",
            'state' => $state,
            'count' => count($prs),
            'prs'   => $prs,
        ]);
    }
}
