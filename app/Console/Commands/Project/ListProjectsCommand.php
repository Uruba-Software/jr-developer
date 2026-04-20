<?php

namespace App\Console\Commands\Project;

use App\Models\Project;
use Illuminate\Console\Command;

/**
 * T28 — ListProjectsCommand
 *
 * Lists all projects with their status and channel mappings.
 *
 * Usage:
 *   php artisan jr:project:list
 *   php artisan jr:project:list --all   # include inactive
 */
class ListProjectsCommand extends Command
{
    protected $signature   = 'jr:project:list {--all : Include inactive projects}';
    protected $description = 'List all configured projects';

    public function handle(): int
    {
        $query = Project::query()->with('platformConnections');

        if (!$this->option('all')) {
            $query->where('is_active', true);
        }

        $projects = $query->get();

        if ($projects->isEmpty()) {
            $this->warn('No projects found.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Mode', 'Path', 'Channels', 'Active'],
            $projects->map(function (Project $project) {
                $channels = $project->platformConnections
                    ->where('is_active', true)
                    ->map(fn ($c) => "{$c->platform}:{$c->channel_id}")
                    ->implode(', ');

                return [
                    $project->id,
                    $project->name,
                    $project->operating_mode->value,
                    $project->local_path ?? '—',
                    $channels ?: '—',
                    $project->is_active ? '✓' : '✗',
                ];
            }),
        );

        return self::SUCCESS;
    }
}
