<?php

namespace App\Tools;

use App\Contracts\ToolRunner;
use App\DTOs\ToolResult;
use App\Enums\ToolPermission;

/**
 * T13 — ReadFileTool
 *
 * Reads a file from the configured project path.
 * Token-aware: if the file exceeds 200 lines, only the requested section is returned.
 */
class ReadFileTool implements ToolRunner
{
    public const NAME = 'read_file';

    /**
     * Lines per section when the file is large.
     */
    private const SECTION_SIZE = 200;

    /**
     * @param  string  $projectPath  Absolute path to the project root
     */
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
     * @param  array{path: string, offset?: int, lines?: int}  $params
     */
    public function run(string $tool, array $params): ToolResult
    {
        $relativePath = $params['path'] ?? '';

        if ($relativePath === '') {
            return ToolResult::fail('Parameter "path" is required.');
        }

        $absolutePath = $this->resolve($relativePath);

        if ($absolutePath === null) {
            return ToolResult::fail("Path traversal detected: {$relativePath}");
        }

        if (!file_exists($absolutePath)) {
            return ToolResult::fail("File not found: {$relativePath}");
        }

        if (!is_file($absolutePath)) {
            return ToolResult::fail("Path is not a file: {$relativePath}");
        }

        $lines = file($absolutePath, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return ToolResult::fail("Could not read file: {$relativePath}");
        }

        $totalLines = count($lines);
        $offset     = max(0, (int) ($params['offset'] ?? 0));
        $limit      = isset($params['lines'])
            ? min((int) $params['lines'], self::SECTION_SIZE)
            : self::SECTION_SIZE;

        // If file fits within one section and no explicit offset, return all
        if ($totalLines <= self::SECTION_SIZE && $offset === 0) {
            $content = implode("\n", $lines);

            return ToolResult::ok([
                'path'        => $relativePath,
                'total_lines' => $totalLines,
                'offset'      => 0,
                'returned'    => $totalLines,
                'content'     => $content,
            ]);
        }

        // Large file — return the requested section
        $slice   = array_slice($lines, $offset, $limit);
        $content = implode("\n", $slice);

        return ToolResult::ok([
            'path'        => $relativePath,
            'total_lines' => $totalLines,
            'offset'      => $offset,
            'returned'    => count($slice),
            'has_more'    => ($offset + $limit) < $totalLines,
            'next_offset' => ($offset + $limit) < $totalLines ? $offset + $limit : null,
            'content'     => $content,
            'hint'        => $totalLines > self::SECTION_SIZE
                ? "File has {$totalLines} lines. Use offset parameter to read further sections."
                : null,
        ]);
    }

    /**
     * Resolve a relative path against the project root, rejecting path traversal.
     */
    private function resolve(string $relativePath): ?string
    {
        $absolute = realpath($this->projectPath . DIRECTORY_SEPARATOR . ltrim($relativePath, '/'));

        if ($absolute === false) {
            // File may not exist yet — build path manually and check prefix
            $candidate = rtrim($this->projectPath, '/') . '/' . ltrim($relativePath, '/');
            $candidate = str_replace(['/./', '//'], '/', $candidate);

            // Reject if path escapes project root
            if (!str_starts_with($candidate, rtrim($this->projectPath, '/') . '/')) {
                return null;
            }

            return $candidate;
        }

        if (!str_starts_with($absolute, rtrim($this->projectPath, '/'))) {
            return null;
        }

        return $absolute;
    }
}
