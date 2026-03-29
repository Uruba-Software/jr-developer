<?php

namespace App\Tools;

use App\Contracts\ToolRunner;
use App\DTOs\ToolResult;
use App\Enums\ToolPermission;
use Illuminate\Support\Facades\Http;

/**
 * T15 — CreatePRTool
 *
 * Creates a pull request on GitHub via the API.
 * If a PR template exists at .github/pull_request_template.md it is pre-filled.
 * Permission: DEPLOY — requires explicit user approval.
 */
class CreatePRTool implements ToolRunner
{
    public const NAME = 'create_pr';

    public function __construct(
        private readonly string $githubToken,
        private readonly string $owner,
        private readonly string $repo,
        private readonly string $projectPath,
    ) {}

    public function supports(string $tool): bool
    {
        return $tool === self::NAME;
    }

    public function permission(): ToolPermission
    {
        return ToolPermission::Deploy;
    }

    /**
     * @param  array{title: string, head?: string, base?: string, body?: string, draft?: bool}  $params
     */
    public function run(string $tool, array $params): ToolResult
    {
        $title = $params['title'] ?? '';
        $base  = $params['base'] ?? 'main';
        $draft = (bool) ($params['draft'] ?? false);

        if ($title === '') {
            return ToolResult::fail('Parameter "title" is required.');
        }

        // Detect current branch if head not provided
        $head = $params['head'] ?? $this->currentBranch();

        if ($head === null || $head === 'HEAD') {
            return ToolResult::fail('Could not determine source branch. Provide "head" parameter.');
        }

        $body = $params['body'] ?? $this->loadTemplate();

        $response = Http::withToken($this->githubToken)
            ->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->post("https://api.github.com/repos/{$this->owner}/{$this->repo}/pulls", [
                'title' => $title,
                'head'  => $head,
                'base'  => $base,
                'body'  => $body,
                'draft' => $draft,
            ]);

        if (!$response->successful()) {
            $error = $response->json()['message'] ?? $response->body();

            return ToolResult::fail("GitHub API error: {$response->status()} — {$error}");
        }

        $pr = $response->json();

        return ToolResult::ok([
            'number' => $pr['number'],
            'title'  => $pr['title'],
            'url'    => $pr['html_url'],
            'head'   => $head,
            'base'   => $base,
            'draft'  => $draft,
        ]);
    }

    private function loadTemplate(): string
    {
        $paths = [
            $this->projectPath . '/.github/pull_request_template.md',
            $this->projectPath . '/.github/PULL_REQUEST_TEMPLATE.md',
            $this->projectPath . '/PULL_REQUEST_TEMPLATE.md',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return file_get_contents($path) ?: '';
            }
        }

        return '';
    }

    private function currentBranch(): ?string
    {
        $escaped = escapeshellarg($this->projectPath);
        $branch  = shell_exec("git -C {$escaped} rev-parse --abbrev-ref HEAD 2>/dev/null");

        return $branch !== null ? trim($branch) : null;
    }
}
