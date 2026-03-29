<?php

namespace App\Tools;

use App\Contracts\ToolRunner;
use App\DTOs\ToolResult;
use App\Enums\ToolPermission;

/**
 * T13 — WriteFileTool
 *
 * Writes content to a file under the project path.
 * Generates a unified diff for human review before applying the change.
 *
 * The tool operates in two stages:
 *   1. "preview" (default): returns a diff without writing.
 *   2. "apply": actually writes the file (requires WRITE permission grant from user).
 */
class WriteFileTool implements ToolRunner
{
    public const NAME = 'write_file';

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
     * @param  array{path: string, content: string, apply?: bool}  $params
     */
    public function run(string $tool, array $params): ToolResult
    {
        $relativePath = $params['path'] ?? '';
        $newContent   = $params['content'] ?? null;
        $apply        = (bool) ($params['apply'] ?? false);

        if ($relativePath === '') {
            return ToolResult::fail('Parameter "path" is required.');
        }

        if ($newContent === null) {
            return ToolResult::fail('Parameter "content" is required.');
        }

        $absolutePath = $this->resolve($relativePath);

        if ($absolutePath === null) {
            return ToolResult::fail("Path traversal detected: {$relativePath}");
        }

        // Read existing content for diff
        $oldContent = file_exists($absolutePath) ? (file_get_contents($absolutePath) ?: '') : '';

        $diff = $this->generateDiff($relativePath, $oldContent, $newContent);

        if (!$apply) {
            // Preview mode — return diff without writing
            return ToolResult::ok([
                'path'    => $relativePath,
                'diff'    => $diff,
                'preview' => true,
                'hint'    => 'Review the diff above and call write_file again with apply=true to save.',
            ]);
        }

        // Apply mode — write the file
        $directory = dirname($absolutePath);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                return ToolResult::fail("Could not create directory: " . dirname($relativePath));
            }
        }

        if (file_put_contents($absolutePath, $newContent) === false) {
            return ToolResult::fail("Could not write file: {$relativePath}");
        }

        return ToolResult::ok([
            'path'    => $relativePath,
            'diff'    => $diff,
            'applied' => true,
            'lines'   => substr_count($newContent, "\n") + 1,
        ]);
    }

    /**
     * Generate a unified diff between old and new content.
     */
    private function generateDiff(string $path, string $oldContent, string $newContent): string
    {
        if ($oldContent === $newContent) {
            return '(no changes)';
        }

        $oldFile = tempnam(sys_get_temp_dir(), 'jrdev_old_');
        $newFile = tempnam(sys_get_temp_dir(), 'jrdev_new_');

        file_put_contents($oldFile, $oldContent);
        file_put_contents($newFile, $newContent);

        $escapedOld  = escapeshellarg($oldFile);
        $escapedNew  = escapeshellarg($newFile);
        $escapedPath = escapeshellarg($path);

        $diff = shell_exec("diff -u --label a/{$path} --label b/{$path} {$escapedOld} {$escapedNew} 2>/dev/null");

        unlink($oldFile);
        unlink($newFile);

        return $diff ?? "(diff unavailable)";
    }

    private function resolve(string $relativePath): ?string
    {
        $root      = rtrim($this->projectPath, '/');
        $candidate = $root . '/' . ltrim($relativePath, '/');

        // Normalize without requiring the file to exist
        $parts    = explode('/', $candidate);
        $resolved = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                if (empty($resolved)) {
                    return null; // traversal above root
                }
                array_pop($resolved);
            } else {
                $resolved[] = $part;
            }
        }

        $absolute = '/' . implode('/', $resolved);

        if (!str_starts_with($absolute, $root . '/') && $absolute !== $root) {
            return null;
        }

        return $absolute;
    }
}
