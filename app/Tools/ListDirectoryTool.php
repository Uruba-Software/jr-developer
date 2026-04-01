<?php

namespace App\Tools;

use App\Contracts\ToolRunner;
use App\DTOs\ToolResult;
use App\Enums\ToolPermission;

/**
 * T13 — ListDirectoryTool
 *
 * Lists the contents of a directory under the project path.
 * Respects .gitignore by reading the file and excluding matching entries.
 */
class ListDirectoryTool implements ToolRunner
{
    public const NAME = 'list_directory';

    private const MAX_ENTRIES = 200;

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
     * @param  array{path?: string}  $params
     */
    public function run(string $tool, array $params): ToolResult
    {
        $relativePath = $params['path'] ?? '.';
        $absolutePath = $this->resolve($relativePath);

        if ($absolutePath === 'traversal') {
            return ToolResult::fail("Path traversal detected: {$relativePath}");
        }

        if ($absolutePath === null || !is_dir($absolutePath)) {
            return ToolResult::fail("Directory not found: {$relativePath}");
        }

        $ignored  = $this->loadGitignorePatterns();
        $entries  = [];
        $iterator = new \DirectoryIterator($absolutePath);

        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }

            $name = $item->getFilename();

            if ($this->isIgnored($name, $ignored)) {
                continue;
            }

            $entries[] = [
                'name' => $name,
                'type' => $item->isDir() ? 'directory' : 'file',
                'size' => $item->isFile() ? $item->getSize() : null,
            ];
        }

        usort($entries, static function (array $a, array $b): int {
            // Directories first, then alphabetical
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }

            return strcmp($a['name'], $b['name']);
        });

        $truncated = count($entries) > self::MAX_ENTRIES;
        $entries   = array_slice($entries, 0, self::MAX_ENTRIES);

        return ToolResult::ok([
            'path'      => $relativePath,
            'entries'   => $entries,
            'count'     => count($entries),
            'truncated' => $truncated,
        ]);
    }

    /** @return string[] */
    private function loadGitignorePatterns(): array
    {
        $gitignore = $this->projectPath . '/.gitignore';

        if (!file_exists($gitignore)) {
            return [];
        }

        $lines = file($gitignore, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        return array_values(array_filter($lines, static fn (string $l) => !str_starts_with($l, '#')));
    }

    /**
     * Simple pattern match against gitignore rules (basename only, no recursive globs).
     *
     * @param  string[]  $patterns
     */
    private function isIgnored(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = rtrim($pattern, '/');

            if (fnmatch($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns resolved absolute path, or:
     *   - 'traversal' string if path escapes project root
     *   - null if directory does not exist
     */
    private function resolve(string $relativePath): string|null
    {
        $root      = rtrim($this->projectPath, '/');
        $candidate = $root . '/' . ltrim($relativePath === '.' ? '' : $relativePath, '/');
        $candidate = rtrim($candidate, '/') ?: $root;

        // Normalize the candidate without requiring it to exist
        $parts    = explode('/', $candidate);
        $resolved = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                if (empty($resolved)) {
                    return 'traversal';
                }
                array_pop($resolved);
            } else {
                $resolved[] = $part;
            }
        }

        $normalized = '/' . implode('/', $resolved);

        if (!str_starts_with($normalized . '/', $root . '/') && $normalized !== $root) {
            return 'traversal';
        }

        // Now check if it actually exists
        $real = realpath($normalized);

        if ($real === false) {
            return null;
        }

        // Double-check after realpath (symlink resolution)
        if (!str_starts_with($real, $root)) {
            return 'traversal';
        }

        return $real;
    }
}
