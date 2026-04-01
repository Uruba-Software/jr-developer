<?php

namespace App\Tools;

use App\Contracts\ToolRunner;
use App\DTOs\ToolResult;
use App\Enums\ToolPermission;

/**
 * T14 — GitDiffTool
 *
 * Returns a formatted git diff for the working tree or between two refs.
 */
class GitDiffTool implements ToolRunner
{
    public const NAME = 'git_diff';

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

    /**
     * @param  array{file?: string, base?: string, head?: string, staged?: bool}  $params
     */
    public function run(string $tool, array $params): ToolResult
    {
        if (!$this->isGitRepo()) {
            return ToolResult::fail("Not a git repository: {$this->projectPath}");
        }

        $staged = (bool) ($params['staged'] ?? false);
        $file   = $params['file'] ?? null;
        $base   = $params['base'] ?? null;
        $head   = $params['head'] ?? null;

        $args = '';

        if ($staged) {
            $args .= ' --cached';
        }

        if ($base !== null && $head !== null) {
            $args .= ' ' . escapeshellarg($base) . '..' . escapeshellarg($head);
        } elseif ($base !== null) {
            $args .= ' ' . escapeshellarg($base);
        }

        if ($file !== null) {
            $args .= ' -- ' . escapeshellarg($file);
        }

        $diff = $this->gitRaw("diff --unified=3{$args}");

        if ($diff === null) {
            return ToolResult::fail('Could not retrieve diff.');
        }

        $diff = trim($diff);

        if ($diff === '') {
            return ToolResult::ok([
                'diff'  => '(no changes)',
                'empty' => true,
            ]);
        }

        return ToolResult::ok([
            'diff'       => $diff,
            'empty'      => false,
            'char_count' => strlen($diff),
        ]);
    }

    private function git(string $subCommand): ?string
    {
        $escaped = escapeshellarg($this->projectPath);

        return shell_exec("git -C {$escaped} {$subCommand} 2>/dev/null");
    }

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
