<?php

namespace App\Tools;

use App\Contracts\ToolRunner;
use App\DTOs\ToolResult;
use App\Enums\ToolPermission;

/**
 * T13 — SearchInFilesTool
 *
 * Grep-based search within the project. Returns matched lines with context.
 * Always prefer this over ReadFileTool when looking for a specific identifier.
 */
class SearchInFilesTool implements ToolRunner
{
    public const NAME = 'search_in_files';

    private const MAX_MATCHES = 50;

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
     * @param  array{pattern: string, path?: string, context_lines?: int, case_insensitive?: bool}  $params
     */
    public function run(string $tool, array $params): ToolResult
    {
        $pattern = $params['pattern'] ?? '';

        if ($pattern === '') {
            return ToolResult::fail('Parameter "pattern" is required.');
        }

        $searchPath      = $this->resolvePath($params['path'] ?? '.');
        $contextLines    = max(0, min(10, (int) ($params['context_lines'] ?? 2)));
        $caseInsensitive = (bool) ($params['case_insensitive'] ?? false);

        if ($searchPath === null) {
            return ToolResult::fail('Path traversal detected.');
        }

        // Build grep command — use -r for directories, nothing for single files
        $flags  = '-n --include="*.php" --include="*.js" --include="*.ts" --include="*.vue"';
        $flags .= ' --include="*.json" --include="*.env*" --include="*.md" --include="*.yaml"';
        $flags .= " -C {$contextLines}";

        if ($caseInsensitive) {
            $flags .= ' -i';
        }

        if (is_dir($searchPath)) {
            $flags .= ' -r';
        }

        $escapedPattern = escapeshellarg($pattern);
        $escapedPath    = escapeshellarg($searchPath);

        $command = "grep {$flags} {$escapedPattern} {$escapedPath} 2>&1";
        $output  = shell_exec($command);

        if ($output === null || trim($output) === '') {
            return ToolResult::ok([
                'pattern' => $pattern,
                'matches' => [],
                'count'   => 0,
            ]);
        }

        $lines   = explode("\n", trim($output));
        $matches = $this->parseGrepOutput($lines, $searchPath);

        $truncated = count($matches) > self::MAX_MATCHES;
        $matches   = array_slice($matches, 0, self::MAX_MATCHES);

        return ToolResult::ok([
            'pattern'   => $pattern,
            'matches'   => $matches,
            'count'     => count($matches),
            'truncated' => $truncated,
            'hint'      => $truncated
                ? 'Too many results. Narrow your search with a more specific pattern or path.'
                : null,
        ]);
    }

    /**
     * Parse grep output into structured match entries.
     *
     * @param  string[]  $lines
     * @return array<array{file: string, line: int|null, content: string, is_match: bool}>
     */
    private function parseGrepOutput(array $lines, string $absoluteBase): array
    {
        $results  = [];
        $projectRoot = rtrim($this->projectPath, '/') . '/';

        foreach ($lines as $line) {
            if ($line === '--') {
                // Grep separator between match groups — skip
                continue;
            }

            // Format for directories: filename:linenum:content or filename-linenum-content (context)
            if (preg_match('/^(.+?)[:\-](\d+)[:\-](.*)$/s', $line, $m)) {
                $file    = str_replace($absoluteBase . '/', '', $m[1]);
                $file    = str_replace($projectRoot, '', $file);
                $lineNum = (int) $m[2];
                $content = $m[3];
                $isMatch = str_contains($line, ':' . $m[2] . ':');

                $results[] = [
                    'file'     => ltrim($file, '/'),
                    'line'     => $lineNum,
                    'content'  => $content,
                    'is_match' => $isMatch,
                ];
            } else {
                // Single file search — no filename prefix
                $results[] = [
                    'file'     => null,
                    'line'     => null,
                    'content'  => $line,
                    'is_match' => true,
                ];
            }
        }

        return $results;
    }

    private function resolvePath(string $relativePath): ?string
    {
        $candidate = rtrim($this->projectPath, '/') . '/' . ltrim($relativePath === '.' ? '' : $relativePath, '/');
        $candidate = rtrim($candidate, '/') ?: $this->projectPath;

        $real = realpath($candidate);

        if ($real === false) {
            return null;
        }

        if (!str_starts_with($real, rtrim($this->projectPath, '/'))) {
            return null;
        }

        return $real;
    }
}
