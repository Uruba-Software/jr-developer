<?php

namespace App\Services\MeetingNotes;

use App\DTOs\JiraIssue;
use App\DTOs\MeetingNote;
use App\Services\Jira\JiraSprintService;
use Carbon\Carbon;

/**
 * T25 — MeetingNoteGenerator
 *
 * Generates structured meeting notes (sprint review, retro, planning, sync)
 * from Jira sprint and issue data.
 *
 * Issue categorisation by status (case-insensitive):
 *   - Done / Closed / Resolved                          → completed
 *   - In Progress / In Review / Under Review / Review   → inProgress
 *   - Blocked (or has "blocked" label)                  → blockers
 *   - Everything else (To Do / Open / Backlog / etc.)   → upcoming
 */
class MeetingNoteGenerator
{
    private const DONE_STATUSES = ['done', 'closed', 'resolved'];

    private const IN_PROGRESS_STATUSES = [
        'in progress', 'in review', 'under review', 'review',
    ];

    public function __construct(
        private readonly JiraSprintService $sprints,
    ) {}

    /**
     * Generate a meeting note for the active sprint on the given board.
     *
     * @param  int         $boardId      Jira board ID
     * @param  string      $meetingType  One of MeetingNote::TYPE_* constants
     * @param  Carbon|null $date         Meeting date (defaults to today)
     */
    public function generate(
        int     $boardId,
        string  $meetingType = MeetingNote::TYPE_SPRINT_REVIEW,
        ?Carbon $date = null,
    ): MeetingNote {
        $date   = $date ?? Carbon::today();
        $sprint = $this->sprints->getActiveSprint($boardId);
        $issues = $sprint !== null
            ? $this->sprints->getSprintIssues($sprint->id)
            : [];

        [$completed, $inProgress, $upcoming, $blockers] = $this->categorise($issues);

        return new MeetingNote(
            meetingType: $meetingType,
            date:        $date,
            sprint:      $sprint,
            completed:   $completed,
            inProgress:  $inProgress,
            upcoming:    $upcoming,
            blockers:    $blockers,
        );
    }

    /**
     * @param  JiraIssue[] $issues
     * @return array{JiraIssue[], JiraIssue[], JiraIssue[], JiraIssue[]}
     *         [completed, inProgress, upcoming, blockers]
     */
    private function categorise(array $issues): array
    {
        $completed  = [];
        $inProgress = [];
        $upcoming   = [];
        $blockers   = [];

        foreach ($issues as $issue) {
            $status  = strtolower(trim($issue->status));
            $isBlocked = in_array('blocked', array_map('strtolower', $issue->labels))
                || $status === 'blocked';

            if ($isBlocked) {
                $blockers[] = $issue;
                continue;
            }

            if (in_array($status, self::DONE_STATUSES, true)) {
                $completed[] = $issue;
            } elseif (in_array($status, self::IN_PROGRESS_STATUSES, true)) {
                $inProgress[] = $issue;
            } else {
                $upcoming[] = $issue;
            }
        }

        return [$completed, $inProgress, $upcoming, $blockers];
    }
}
