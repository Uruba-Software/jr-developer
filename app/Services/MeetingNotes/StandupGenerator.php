<?php

namespace App\Services\MeetingNotes;

use App\DTOs\StandupNote;
use App\Services\Jira\JiraService;
use Carbon\Carbon;

/**
 * T24 — StandupGenerator
 *
 * Generates a daily standup note by querying Jira for:
 *   - Issues completed yesterday (status changed to Done)
 *   - Issues currently in progress (open sprint, status = In Progress)
 *   - Blocked issues (open sprint, "blocked" label)
 */
class StandupGenerator
{
    public function __construct(
        private readonly JiraService $jira,
    ) {}

    /**
     * Generate a standup note for the given assignee account ID.
     *
     * @param  string      $assigneeAccountId  Jira user account ID
     * @param  string      $assigneeName       Display name for the note header
     * @param  Carbon|null $date               Date to generate for (defaults to today)
     */
    public function generate(
        string  $assigneeAccountId,
        string  $assigneeName,
        ?Carbon $date = null,
    ): StandupNote {
        $date      = $date ?? Carbon::today();
        $yesterday = $date->copy()->subWeekday(); // skip weekends going back

        $completed  = $this->fetchCompleted($assigneeAccountId, $yesterday, $date);
        $inProgress = $this->fetchInProgress($assigneeAccountId);
        $blockers   = $this->fetchBlockers($assigneeAccountId);

        return new StandupNote(
            date:          $date,
            assigneeName:  $assigneeName,
            completed:     $completed,
            inProgress:    $inProgress,
            blockers:      $blockers,
        );
    }

    /** @return \App\DTOs\JiraIssue[] */
    private function fetchCompleted(
        string $assigneeId,
        Carbon $since,
        Carbon $until,
    ): array {
        $sinceStr = $since->format('Y-m-d');
        $untilStr = $until->format('Y-m-d');

        $jql = "assignee = \"{$assigneeId}\" "
            . "AND status changed to Done AFTER \"{$sinceStr}\" BEFORE \"{$untilStr}\" "
            . "ORDER BY updated DESC";

        return $this->jira->searchIssues($jql, 20);
    }

    /** @return \App\DTOs\JiraIssue[] */
    private function fetchInProgress(string $assigneeId): array
    {
        $jql = "assignee = \"{$assigneeId}\" "
            . "AND sprint in openSprints() "
            . "AND status = \"In Progress\" "
            . "ORDER BY priority ASC";

        return $this->jira->searchIssues($jql, 10);
    }

    /** @return \App\DTOs\JiraIssue[] */
    private function fetchBlockers(string $assigneeId): array
    {
        $jql = "assignee = \"{$assigneeId}\" "
            . "AND sprint in openSprints() "
            . "AND labels = \"blocked\" "
            . "ORDER BY priority ASC";

        return $this->jira->searchIssues($jql, 10);
    }
}
