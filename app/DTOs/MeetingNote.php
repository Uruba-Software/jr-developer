<?php

namespace App\DTOs;

use Carbon\Carbon;

/**
 * T25 — MeetingNote DTO
 *
 * Holds structured data for a dev meeting note (sprint review, retro, planning, sync).
 */
readonly class MeetingNote
{
    public const TYPE_SPRINT_REVIEW = 'sprint-review';
    public const TYPE_RETROSPECTIVE = 'retrospective';
    public const TYPE_PLANNING      = 'planning';
    public const TYPE_SYNC          = 'sync';

    /**
     * @param  JiraIssue[] $completed   Issues done this sprint
     * @param  JiraIssue[] $inProgress  Issues in progress
     * @param  JiraIssue[] $upcoming    Issues not yet started
     * @param  JiraIssue[] $blockers    Issues flagged as blocked
     */
    public function __construct(
        public string      $meetingType,
        public Carbon      $date,
        public ?JiraSprint $sprint,
        public array       $completed,
        public array       $inProgress,
        public array       $upcoming,
        public array       $blockers,
    ) {}

    /**
     * Render a Slack-ready markdown message for the meeting note.
     */
    public function toSlackMessage(): string
    {
        $title = match ($this->meetingType) {
            self::TYPE_SPRINT_REVIEW => '🏁 Sprint Review',
            self::TYPE_RETROSPECTIVE => '🔁 Retrospective',
            self::TYPE_PLANNING      => '📋 Sprint Planning',
            default                  => '💬 Dev Sync',
        };

        $lines = [
            "*{$title} — {$this->date->format('l, d M Y')}*",
        ];

        if ($this->sprint !== null) {
            $lines[] = "📦 Sprint: *{$this->sprint->name}*";
        }

        $lines[] = '';

        // Progress summary
        $total     = count($this->completed) + count($this->inProgress) + count($this->upcoming);
        $doneCount = count($this->completed);
        $lines[]   = "*📊 Sprint Progress: {$doneCount}/{$total} done*";
        $lines[]   = '';

        // Completed
        if (!empty($this->completed)) {
            $lines[] = '*✅ Completed*';
            foreach ($this->completed as $issue) {
                $sp      = $issue->storyPoints ? " ({$issue->storyPoints}pt)" : '';
                $lines[] = "• <{$issue->url}|{$issue->key}> {$issue->summary}{$sp}";
            }
            $lines[] = '';
        }

        // In Progress
        if (!empty($this->inProgress)) {
            $lines[] = '*🔨 In Progress*';
            foreach ($this->inProgress as $issue) {
                $assignee = $issue->assignee ? " — _{$issue->assignee}_" : '';
                $lines[]  = "• <{$issue->url}|{$issue->key}> {$issue->summary}{$assignee}";
            }
            $lines[] = '';
        }

        // Upcoming (planning)
        if (!empty($this->upcoming) && $this->meetingType === self::TYPE_PLANNING) {
            $lines[] = '*📌 Up Next*';
            foreach ($this->upcoming as $issue) {
                $lines[] = "• <{$issue->url}|{$issue->key}> {$issue->summary}";
            }
            $lines[] = '';
        }

        // Blockers
        if (!empty($this->blockers)) {
            $lines[] = '*🚧 Blockers*';
            foreach ($this->blockers as $issue) {
                $assignee = $issue->assignee ? " — _{$issue->assignee}_" : '';
                $lines[]  = "• <{$issue->url}|{$issue->key}> {$issue->summary}{$assignee}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'meeting_type' => $this->meetingType,
            'date'         => $this->date->toDateString(),
            'sprint'       => $this->sprint?->toArray(),
            'completed'    => array_map(fn (JiraIssue $i) => $i->toArray(), $this->completed),
            'in_progress'  => array_map(fn (JiraIssue $i) => $i->toArray(), $this->inProgress),
            'upcoming'     => array_map(fn (JiraIssue $i) => $i->toArray(), $this->upcoming),
            'blockers'     => array_map(fn (JiraIssue $i) => $i->toArray(), $this->blockers),
        ];
    }
}
