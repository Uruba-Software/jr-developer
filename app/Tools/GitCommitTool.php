<?php

namespace App\Tools;

use App\Contracts\ToolRunner;
use App\DTOs\ToolResult;
use App\Enums\ToolPermission;

/**
 * T14 — GitCommitTool
 *
 * Stages and commits changes in the project repository.
 * Permission: DEPLOY — requires explicit user approval via messaging platform.
 */
class GitCommitTool implements ToolRunner
{
    public const NAME = 'git_commit';

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
     * @param  array{message: string, files?: string[]}  $params
     */
    public function run(string $tool, array $params): ToolResult
    {
        if (!$this->isGitRepo()) {
            return ToolResult::fail("Not a git repository: {$this->projectPath}");
        }

        $message = $params['message'] ?? '';
        $files   = $params['files'] ?? [];

        if ($message === '') {
            return ToolResult::fail('Parameter "message" is required.');
        }

        // Stage specific files or all changes
        if (!empty($files)) {
            foreach ($files as $file) {
                $safeFile = $this->resolveSafe($file);

                if ($safeFile === null) {
                    return ToolResult::fail("Path traversal detected: {$file}");
                }

                $this->git('add ' . escapeshellarg($safeFile));
            }
        } else {
            $this->git('add -A');
        }

        // Check if there is anything to commit
        $staged = trim($this->git('diff --cached --name-only') ?? '');

        if ($staged === '') {
            return ToolResult::fail('Nothing to commit. Stage changes first or verify files were written.');
        }

        $escapedMessage = escapeshellarg($message);
        $output         = $this->git("commit -m {$escapedMessage} 2>&1");

        if ($output === null || str_contains($output, 'error:') || str_contains($output, 'fatal:')) {
            return ToolResult::fail('Commit failed: ' . ($output ?? 'unknown error'));
        }

        $hash = trim($this->git('rev-parse --short HEAD') ?? '');

        return ToolResult::ok([
            'hash'    => $hash,
            'message' => $message,
            'files'   => array_filter(explode("\n", $staged)),
            'output'  => trim($output),
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

    private function resolveSafe(string $relativePath): ?string
    {
        $root      = rtrim($this->projectPath, '/');
        $candidate = $root . '/' . ltrim($relativePath, '/');
        $parts     = explode('/', $candidate);
        $resolved  = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                if (empty($resolved)) {
                    return null;
                }
                array_pop($resolved);
            } else {
                $resolved[] = $part;
            }
        }

        $absolute = '/' . implode('/', $resolved);

        return str_starts_with($absolute, $root . '/') ? $absolute : null;
    }
}
