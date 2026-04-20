<?php

namespace App\Console\Commands\Project;

use App\Enums\OperatingMode;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Console\Command;

/**
 * T28 — AddProjectCommand
 *
 * Adds a new project from the CLI with interactive prompts.
 *
 * Usage:
 *   php artisan jr:project:add
 *   php artisan jr:project:add --name="My App" --path=/var/www/myapp --mode=agent
 */
class AddProjectCommand extends Command
{
    protected $signature   = 'jr:project:add
                                {--name=           : Project name}
                                {--path=           : Absolute path to the project}
                                {--mode=agent      : Operating mode (manual|agent|cloud)}
                                {--user=           : User ID to own the project (default: first user)}';

    protected $description = 'Add a new project';

    public function __construct(
        private readonly ProjectRepositoryInterface $projects,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Project name');
        $path = $this->option('path') ?: $this->ask('Absolute path to project');
        $mode = $this->option('mode') ?: $this->choice(
            'Operating mode',
            ['manual', 'agent', 'cloud'],
            'agent',
        );

        $operatingMode = OperatingMode::tryFrom($mode);

        if ($operatingMode === null) {
            $this->error("Invalid mode '{$mode}'. Use: manual, agent, or cloud.");
            return self::FAILURE;
        }

        // Resolve owner
        $userId = $this->option('user');
        $user   = $userId
            ? User::find($userId)
            : User::first();

        if ($user === null) {
            $this->error('No user found. Run php artisan jr:setup first.');
            return self::FAILURE;
        }

        if ($this->projects->findBySlug(\Illuminate\Support\Str::slug($name), $user->id) !== null) {
            $this->error("A project named \"{$name}\" already exists for this user.");
            return self::FAILURE;
        }

        $project = Project::create([
            'user_id'        => $user->id,
            'name'           => $name,
            'local_path'     => $path,
            'operating_mode' => $operatingMode,
            'is_active'      => true,
        ]);

        $this->info("✓ Project \"{$project->name}\" created (ID: {$project->id}, slug: {$project->slug}).");

        return self::SUCCESS;
    }
}
