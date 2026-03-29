<?php

namespace App\Tools;

use App\Contracts\ToolRunner;
use App\DTOs\ToolResult;
use App\Enums\ToolPermission;

/**
 * T14 — GitBranchTool
 *
 * Creates or switches to a branch in the project repository.
 */
class GitBranchTool implements ToolRunner
{
    public const NAME = 'git_branch';

    public function __construct(
        private readonly string $projectPath,
    ) {}

    public function supports(string $tool): bool
    {
        return $tool === self::NAME;
    }

    public function permission(): ToolPermission
    {
        return ToolPermission::Write;
    }

    /**
     * @param  array{name: string, create?: bool, base?: string}  $params
     */
    public function run(string $tool, array $params): ToolResult
    {
        if (!$this->isGitRepo()) {
            return ToolResult::fail("Not a git repository: {$this->projectPath}");
        }

        $name   = $params['name'] ?? '';
        $create = (bool) ($params['create'] ?? false);
        $base   = $params['base'] ?? null;

        if ($name === '') {
            return ToolResult::fail('Parameter "name" is required.');
        }

        if (!preg_match('/^[a-zA-Z0-9\/_\-\.]+$/', $name)) {
            return ToolResult::fail("Invalid branch name: {$name}");
        }

        if ($create) {
            $baseArg = $base !== null ? ' ' . escapeshellarg($base) : '';
            $output  = $this->git('checkout -b ' . escapeshellarg($name) . $baseArg);

            if ($output === null) {
                return ToolResult::fail("Could not create branch: {$name}");
            }

            return ToolResult::ok([
                'action'  => 'created',
                'branch'  => $name,
                'base'    => $base,
                'output'  => trim($output),
            ]);
        }

        // Switch to existing branch
        $output = $this->git('checkout ' . escapeshellarg($name));

        if ($output === null) {
            return ToolResult::fail("Could not switch to branch: {$name}");
        }

        return ToolResult::ok([
            'action' => 'switched',
            'branch' => $name,
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
