<?php

namespace App\Console\Commands\Project;

use App\Models\Project;
use Illuminate\Console\Command;

/**
 * T28 — RemoveProjectCommand
 *
 * Deactivates (soft-removes) a project by slug or ID.
 * Use --delete to permanently remove it from the database.
 *
 * Usage:
 *   php artisan jr:project:remove my-app
 *   php artisan jr:project:remove 3 --delete
 */
class RemoveProjectCommand extends Command
{
    protected $signature   = 'jr:project:remove
                                {project : Project slug or ID}
                                {--delete : Permanently delete instead of deactivating}';

    protected $description = 'Remove (deactivate) a project';

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

        if ($this->option('delete')) {
            if (!$this->confirm("Permanently delete \"{$project->name}\"? This cannot be undone.")) {
                $this->line('Cancelled.');
                return self::SUCCESS;
            }

            $project->forceDelete();
            $this->info("✓ Project \"{$project->name}\" permanently deleted.");
        } else {
            $project->update(['is_active' => false]);
            $this->info("✓ Project \"{$project->name}\" deactivated. Use --delete to remove it permanently.");
        }

        return self::SUCCESS;
    }
}
