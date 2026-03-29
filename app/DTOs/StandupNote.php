<?php

namespace App\DTOs;

use Carbon\Carbon;

/**
 * T24 — StandupNote DTO
 *
 * Holds the structured data for a daily standup note generated from Jira.
 */
readonly class StandupNote
{
    /**
     * @param  JiraIssue[] $completed   Issues marked done since yesterday
     * @param  JiraIssue[] $inProgress  Issues currently in progress
     * @param  JiraIssue[] $blockers    Issues flagged as blocked
     */
    public function __construct(
        public Carbon  $date,
        public string  $assigneeName,
        public array   $completed,
        public array   $inProgress,
        public array   $blockers,
    ) {}

    /**
     * Render a Slack-ready markdown message for the standup.
     */
    public function toSlackMessage(): string
    {
        $lines = [
            "*Daily Standup — {$this->date->format('l, d M Y')}*",
            "👤 {$this->assigneeName}",
            '',
        ];

        // Yesterday
        $lines[] = '*✅ Yesterday*';

        if (empty($this->completed)) {
            $lines[] = '• _(nothing completed)_';
        } else {
            foreach ($this->completed as $issue) {
                $lines[] = "• <{$issue->url}|{$issue->key}> {$issue->summary}";
            }
        }

        $lines[] = '';

        // Today
        $lines[] = '*🔨 Today*';

        if (empty($this->inProgress)) {
            $lines[] = '• _(nothing planned)_';
        } else {
            foreach ($this->inProgress as $issue) {
                $lines[] = "• <{$issue->url}|{$issue->key}> {$issue->summary}";
            }
        }

        $lines[] = '';

        // Blockers
        $lines[] = '*🚧 Blockers*';

        if (empty($this->blockers)) {
            $lines[] = '• _(no blockers)_';
        } else {
            foreach ($this->blockers as $issue) {
                $lines[] = "• <{$issue->url}|{$issue->key}> {$issue->summary}";
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
            'date'        => $this->date->toDateString(),
            'assignee'    => $this->assigneeName,
            'completed'   => array_map(fn (JiraIssue $i) => $i->toArray(), $this->completed),
            'in_progress' => array_map(fn (JiraIssue $i) => $i->toArray(), $this->inProgress),
            'blockers'    => array_map(fn (JiraIssue $i) => $i->toArray(), $this->blockers),
        ];
    }
}
