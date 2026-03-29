<?php

namespace App\Console\Commands\MeetingNotes;

use App\Contracts\MessagingPlatform;
use App\Models\Project;
use App\Services\Jira\JiraServiceFactory;
use App\Services\MeetingNotes\StandupGenerator;
use Illuminate\Console\Command;

/**
 * T26 — SendStandupCommand
 *
 * Sends a daily standup note to Slack for all active projects
 * that have Jira configured.
 *
 * Usage:
 *   php artisan jr:standup              # all active projects
 *   php artisan jr:standup --project=1  # specific project by ID
 */
class SendStandupCommand extends Command
{
    protected $signature   = 'jr:standup
                                {--project= : Project ID to send standup for (default: all active)}';

    protected $description = 'Generate and send a daily standup note to Slack from Jira data';

    public function __construct(
        private readonly JiraServiceFactory $jiraFactory,
        private readonly MessagingPlatform  $messaging,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $projects = $this->resolveProjects();

        if ($projects->isEmpty()) {
            $this->warn('No active projects with Jira configuration found.');
            return self::SUCCESS;
        }

        foreach ($projects as $project) {
            $this->sendStandupForProject($project);
        }

        return self::SUCCESS;
    }

    private function resolveProjects()
    {
        $projectId = $this->option('project');

        if ($projectId !== null) {
            return Project::where('id', $projectId)
                ->where('is_active', true)
                ->get();
        }

        return Project::where('is_active', true)->get();
    }

    private function sendStandupForProject(Project $project): void
    {
        $config = $project->config ?? [];

        // Skip projects without Jira config
        if (empty($config['jira']['assignee_account_id'])) {
            $this->line("  Skipping [{$project->name}]: no Jira assignee configured.");
            return;
        }

        $channel = $config['slack']['channel_id'] ?? config('services.slack.channel_id');

        if (empty($channel)) {
            $this->line("  Skipping [{$project->name}]: no Slack channel configured.");
            return;
        }

        try {
            $jira      = $this->jiraFactory->forProject($project);
            $generator = new StandupGenerator($jira);

            $assigneeId   = $config['jira']['assignee_account_id'];
            $assigneeName = $config['jira']['assignee_name'] ?? $project->user->name ?? 'Team';

            $note    = $generator->generate($assigneeId, $assigneeName);
            $message = $note->toSlackMessage();

            $this->messaging->sendMessage($channel, $message);

            $this->info("  ✓ Standup sent for [{$project->name}].");
        } catch (\Throwable $e) {
            $this->error("  ✗ Failed for [{$project->name}]: {$e->getMessage()}");
            report($e);
        }
    }
}
