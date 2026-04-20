<?php

namespace App\Console\Commands\Project;

use App\Enums\ToolPermission;
use App\Models\Project;
use App\Services\ProjectToolConfigService;
use Illuminate\Console\Command;

/**
 * T29 — ToolConfigCommand
 *
 * View and set per-project tool permission overrides.
 *
 * Usage:
 *   php artisan jr:project:tools my-app                             # list overrides
 *   php artisan jr:project:tools my-app write_file write            # set override
 *   php artisan jr:project:tools my-app git_push deploy             # set override
 *   php artisan jr:project:tools my-app git_commit --reset          # remove override
 */
class ToolConfigCommand extends Command
{
    protected $signature   = 'jr:project:tools
                                {project         : Project slug or ID}
                                {tool?           : Tool name (optional — omit to list all)}
                                {permission?     : Permission level (read|write|exec|deploy|destroy)}
                                {--reset         : Remove the override for the specified tool}';

    protected $description = 'View or set per-project tool permission overrides';

    public function __construct(
        private readonly ProjectToolConfigService $toolConfig,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $identifier = $this->argument('project');

        $project = is_numeric($identifier)
            ? Project::find($identifier)
            : Project::where('slug', $identifier)->first();

        if ($project === null) {
            $this->error("Project \"{$identifier}\" not found.");
            return self::FAILURE;
        }

        $tool       = $this->argument('tool');
        $permission = $this->argument('permission');
        $reset      = $this->option('reset');

        // List overrides
        if ($tool === null) {
            return $this->listOverrides($project);
        }

        // Reset a single override
        if ($reset) {
            $this->toolConfig->removePermission($project, $tool);
            $this->info("✓ Override for \"{$tool}\" removed from \"{$project->name}\".");
            return self::SUCCESS;
        }

        // Set an override
        if ($permission === null) {
            $this->error('Provide a permission level: read, write, exec, deploy, or destroy.');
            return self::FAILURE;
        }

        $toolPermission = ToolPermission::tryFrom($permission);

        if ($toolPermission === null) {
            $this->error("Invalid permission \"{$permission}\". Use: read, write, exec, deploy, destroy.");
            return self::FAILURE;
        }

        if ($toolPermission === ToolPermission::Destroy) {
            $this->error('Cannot set "destroy" as a project-level permission — it is always blocked.');
            return self::FAILURE;
        }

        $this->toolConfig->setPermission($project, $tool, $toolPermission);
        $this->info("✓ Tool \"{$tool}\" set to \"{$permission}\" for \"{$project->name}\".");

        return self::SUCCESS;
    }

    private function listOverrides(Project $project): int
    {
        $overrides = $this->toolConfig->getOverrides($project);

        $this->line("Tool permission overrides for <info>{$project->name}</info>:");

        if (empty($overrides)) {
            $this->line('  (none — all tools use global defaults)');
            return self::SUCCESS;
        }

        $this->table(
            ['Tool', 'Permission'],
            collect($overrides)->map(fn ($perm, $tool) => [$tool, $perm])->values(),
        );

        return self::SUCCESS;
    }
}
