<?php

namespace App\Console\Commands\Project;

use App\Exceptions\ChannelAlreadyMappedException;
use App\Models\Project;
use App\Services\ChannelMappingService;
use Illuminate\Console\Command;

/**
 * T27 — MapChannelCommand
 *
 * Maps a messaging channel to a project.
 *
 * Usage:
 *   php artisan jr:channel:map {project} {platform} {channel_id}
 *   php artisan jr:channel:map my-app slack C0123456789 --name="dev-team"
 */
class MapChannelCommand extends Command
{
    protected $signature   = 'jr:channel:map
                                {project          : Project slug or ID}
                                {platform         : Platform (slack|discord)}
                                {channel_id       : Channel or server ID}
                                {--name=          : Human-readable channel name (optional)}';

    protected $description = 'Map a messaging channel to a project';

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
        $name       = $this->option('name') ?? '';

        if (!in_array($platform, ['slack', 'discord'], true)) {
            $this->error("Invalid platform \"{$platform}\". Use: slack or discord.");
            return self::FAILURE;
        }

        $project = is_numeric($identifier)
            ? Project::find($identifier)
            : Project::where('slug', $identifier)->first();

        if ($project === null) {
            $this->error("Project \"{$identifier}\" not found.");
            return self::FAILURE;
        }

        try {
            $connection = $this->mapping->map($project, $platform, $channelId, $name);

            $this->info("✓ Channel {$channelId} ({$platform}) mapped to \"{$project->name}\".");

            return self::SUCCESS;
        } catch (ChannelAlreadyMappedException $e) {
            $this->error($e->getMessage());
            $this->line('Use jr:channel:unmap to remove the existing mapping first.');
            return self::FAILURE;
        }
    }
}
