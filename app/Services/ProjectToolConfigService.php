<?php

namespace App\Services;

use App\Enums\ToolPermission;
use App\Models\Project;

/**
 * T29 — ProjectToolConfigService
 *
 * Resolves effective tool permissions for a project.
 *
 * Permission resolution order:
 *   1. Per-project override (stored in projects.config['tools'])
 *   2. Global default (from config/jr.php or ToolPermission enum default)
 *
 * Config format (project.config['tools']):
 * {
 *   "write_file":  "write",
 *   "git_commit":  "deploy",
 *   "git_push":    "deploy"
 * }
 */
class ProjectToolConfigService
{
    /**
     * Get the effective permission for a tool on a given project.
     * Falls back to the provided default if no override is set.
     */
    public function getEffectivePermission(
        Project        $project,
        string         $toolName,
        ToolPermission $default = ToolPermission::Read,
    ): ToolPermission {
        $override = $this->getOverrides($project)[$toolName] ?? null;

        if ($override === null) {
            return $default;
        }

        return ToolPermission::tryFrom($override) ?? $default;
    }

    /**
     * Set a per-project tool permission override.
     */
    public function setPermission(
        Project        $project,
        string         $toolName,
        ToolPermission $permission,
    ): void {
        $config = $project->config ?? [];
        $config['tools'][$toolName] = $permission->value;

        $project->update(['config' => $config]);
    }

    /**
     * Remove a per-project tool permission override, reverting to global default.
     */
    public function removePermission(Project $project, string $toolName): void
    {
        $config = $project->config ?? [];
        unset($config['tools'][$toolName]);

        $project->update(['config' => $config]);
    }

    /**
     * Get all tool overrides for a project.
     *
     * @return array<string, string>
     */
    public function getOverrides(Project $project): array
    {
        return $project->config['tools'] ?? [];
    }

    /**
     * Replace all tool overrides for a project at once.
     *
     * @param array<string, string> $overrides  tool_name => ToolPermission::value
     */
    public function setOverrides(Project $project, array $overrides): void
    {
        $config = $project->config ?? [];

        // Validate that all values are valid ToolPermission values
        $validated = [];
        foreach ($overrides as $tool => $value) {
            if (ToolPermission::tryFrom($value) !== null) {
                $validated[$tool] = $value;
            }
        }

        $config['tools'] = $validated;
        $project->update(['config' => $config]);
    }
}
