<?php

namespace App\Console\Commands\Project;

use App\Models\Project;
use App\Services\ChannelMappingService;
use Illuminate\Console\Command;

/**
 * T27 — UnmapChannelCommand
 *
 * Removes the mapping between a channel and a project.
 *
 * Usage:
 *   php artisan jr:channel:unmap my-app slack C0123456789
 */
class UnmapChannelCommand extends Command
{
    protected $signature   = 'jr:channel:unmap
                                {project    : Project slug or ID}
                                {platform   : Platform (slack|discord)}
                                {channel_id : Channel ID to unmap}';

    protected $description = 'Remove a channel mapping from a project';

    public function __construct(
        private readonly ChannelMappingService $mapping,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $identifier = $this->argument('project');
        $platform   = strtolower($this->argument('platform'));
        $channelId  = $this->argument('channel_id');

        $project = is_numeric($identifier)
            ? Project::find($identifier)
            : Project::where('slug', $identifier)->first();

        if ($project === null) {
            $this->error("Project \"{$identifier}\" not found.");
            return self::FAILURE;
        }

        $unmapped = $this->mapping->unmap($project, $platform, $channelId);

        if (!$unmapped) {
            $this->warn("No active mapping found for channel {$channelId} ({$platform}) on \"{$project->name}\".");
            return self::SUCCESS;
        }

        $this->info("✓ Channel {$channelId} ({$platform}) unmapped from \"{$project->name}\".");

        return self::SUCCESS;
    }
}
