<?php

namespace App\Console\Commands\MeetingNotes;

use App\Contracts\MessagingPlatform;
use App\DTOs\MeetingNote;
use App\Models\Project;
use App\Services\Jira\JiraServiceFactory;
use App\Services\MeetingNotes\MeetingNoteGenerator;
use Illuminate\Console\Command;

/**
 * T25 — SendMeetingNotesCommand
 *
 * Generates and sends a dev meeting note (sprint review, retro, planning, sync)
 * to Slack for one or all active projects.
 *
 * Usage:
 *   php artisan jr:meeting-notes                           # sprint-review, all projects
 *   php artisan jr:meeting-notes --type=retrospective      # retro
 *   php artisan jr:meeting-notes --project=1 --type=sync  # specific project + type
 */
class SendMeetingNotesCommand extends Command
{
    protected $signature   = 'jr:meeting-notes
                                {--type=sprint-review : Meeting type (sprint-review|retrospective|planning|sync)}
                                {--project=           : Project ID (default: all active)}';

    protected $description = 'Generate and send dev meeting notes to Slack from Jira sprint data';

    private const VALID_TYPES = [
        MeetingNote::TYPE_SPRINT_REVIEW,
        MeetingNote::TYPE_RETROSPECTIVE,
        MeetingNote::TYPE_PLANNING,
        MeetingNote::TYPE_SYNC,
    ];

    public function __construct(
        private readonly JiraServiceFactory $jiraFactory,
        private readonly MessagingPlatform  $messaging,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $meetingType = $this->option('type');

        if (!in_array($meetingType, self::VALID_TYPES, true)) {
            $this->error("Invalid meeting type: {$meetingType}. Use: " . implode(', ', self::VALID_TYPES));
            return self::FAILURE;
        }

        $projects = $this->resolveProjects();

        if ($projects->isEmpty()) {
            $this->warn('No active projects with Jira configuration found.');
            return self::SUCCESS;
        }

        foreach ($projects as $project) {
            $this->sendNotesForProject($project, $meetingType);
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

    private function sendNotesForProject(Project $project, string $meetingType): void
    {
        $config = $project->config ?? [];

        if (empty($config['jira']['board_id'])) {
            $this->line("  Skipping [{$project->name}]: no Jira board_id configured.");
            return;
        }

        $channel = $config['slack']['channel_id'] ?? config('services.slack.channel_id');

        if (empty($channel)) {
            $this->line("  Skipping [{$project->name}]: no Slack channel configured.");
            return;
        }

        try {
            $this->jiraFactory->forProject($project); // validate credentials
            $sprintService = $this->jiraFactory->sprintForProject($project);
            $generator = new MeetingNoteGenerator($sprintService);

            $boardId = (int) $config['jira']['board_id'];
            $note    = $generator->generate($boardId, $meetingType);
            $message = $note->toSlackMessage();

            $this->messaging->sendMessage($channel, $message);

            $this->info("  ✓ Meeting note ({$meetingType}) sent for [{$project->name}].");
        } catch (\Throwable $e) {
            $this->error("  ✗ Failed for [{$project->name}]: {$e->getMessage()}");
            report($e);
        }
    }
}
