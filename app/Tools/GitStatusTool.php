<?php

namespace App\Tools;

use App\Contracts\ToolRunner;
use App\DTOs\ToolResult;
use App\Enums\ToolPermission;

/**
 * T14 — GitStatusTool
 *
 * Returns the working tree status of the configured project's git repository.
 */
class GitStatusTool implements ToolRunner
{
    public const NAME = 'git_status';

    public function __construct(
        private readonly string $projectPath,
    ) {}

    public function supports(string $tool): bool
    {
        return $tool === self::NAME;
    }

    public function permission(): ToolPermission
    {
        return ToolPermission::Read;
    }

    public function run(string $tool, array $params): ToolResult
    {
        if (!$this->isGitRepo()) {
            return ToolResult::fail("Not a git repository: {$this->projectPath}");
        }

        // Use 2>&1 so failure output is captured; empty string means clean repo
        $raw    = $this->gitRaw('status --porcelain=v1');
        $branch = trim($this->git('rev-parse --abbrev-ref HEAD') ?? 'unknown');

        if ($raw === null) {
            return ToolResult::fail('Could not read git status.');
        }

        $lines   = array_filter(explode("\n", trim($raw)));
        $changed = [];

        foreach ($lines as $line) {
            if (strlen($line) < 4) {
                continue;
            }

            $indexStatus   = $line[0];
            $worktreeStatus = $line[1];
            $filePath      = trim(substr($line, 3));

            $changed[] = [
                'file'           => $filePath,
                'index_status'   => $this->statusLabel($indexStatus),
                'worktree_status' => $this->statusLabel($worktreeStatus),
            ];
        }

        return ToolResult::ok([
            'branch'  => $branch,
            'clean'   => empty($changed),
            'changes' => $changed,
            'count'   => count($changed),
        ]);
    }

    private function statusLabel(string $code): string
    {
        return match ($code) {
            'M'     => 'modified',
            'A'     => 'added',
            'D'     => 'deleted',
            'R'     => 'renamed',
            'C'     => 'copied',
            'U'     => 'unmerged',
            '?'     => 'untracked',
            '!'     => 'ignored',
            ' '     => 'unchanged',
            default => $code,
        };
    }

    /**
     * Run a git command, redirecting stderr to /dev/null.
     * Returns null on command failure (non-zero exit or truly no output possible).
     */
    private function git(string $subCommand): ?string
    {
        $escaped = escapeshellarg($this->projectPath);

        return shell_exec("git -C {$escaped} {$subCommand} 2>/dev/null");
    }

    /**
     * Run a git command capturing both stdout and stderr.
     * Returns empty string on clean-state commands (e.g. git status on clean repo).
     */
    private function gitRaw(string $subCommand): ?string
    {
        $escaped = escapeshellarg($this->projectPath);
        exec("git -C {$escaped} {$subCommand} 2>/dev/null", $lines, $exitCode);

        if ($exitCode !== 0) {
            return null;
        }

        return implode("\n", $lines);
    }

    private function isGitRepo(): bool
    {
        $escaped = escapeshellarg($this->projectPath);

        return shell_exec("git -C {$escaped} rev-parse --git-dir 2>/dev/null") !== null;
    }
}
