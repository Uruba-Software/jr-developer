<?php

namespace App\Tools;

use App\Contracts\ToolRunner;
use App\DTOs\ToolResult;
use App\Enums\ToolPermission;

/**
 * T14 — GitPushTool
 *
 * Pushes the current branch to the remote repository.
 * Permission: DEPLOY — requires explicit user approval.
 */
class GitPushTool implements ToolRunner
{
    public const NAME = 'git_push';

    public function __construct(
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
     * @param  array{remote?: string, branch?: string, set_upstream?: bool}  $params
     */
    public function run(string $tool, array $params): ToolResult
    {
        if (!$this->isGitRepo()) {
            return ToolResult::fail("Not a git repository: {$this->projectPath}");
        }

        $remote      = $params['remote'] ?? 'origin';
        $branch      = $params['branch'] ?? trim($this->git('rev-parse --abbrev-ref HEAD') ?? '');
        $setUpstream = (bool) ($params['set_upstream'] ?? true);

        if ($branch === '' || $branch === 'HEAD') {
            return ToolResult::fail('Could not determine current branch.');
        }

        // Reject force push — Destroy-level action
        $args = escapeshellarg($remote) . ' ' . escapeshellarg($branch);

        if ($setUpstream) {
            $args = '--set-upstream ' . $args;
        }

        $output = $this->git("push {$args} 2>&1");

        if ($output === null) {
            return ToolResult::fail('Push command returned no output.');
        }

        if (str_contains($output, 'error:') || str_contains($output, 'fatal:')) {
            return ToolResult::fail("Push failed: {$output}");
        }

        return ToolResult::ok([
            'remote' => $remote,
            'branch' => $branch,
            'output' => trim($output),
        ]);
    }

    private function git(string $subCommand): ?string
    {
        $escaped = escapeshellarg($this->projectPath);

        return shell_exec("git -C {$escaped} {$subCommand} 2>&1");
    }

    private function isGitRepo(): bool
    {
        $escaped = escapeshellarg($this->projectPath);

        return shell_exec("git -C {$escaped} rev-parse --git-dir 2>/dev/null") !== null;
    }
}
