<?php

namespace App\DTOs;

/**
 * T19 — JiraIssue DTO
 *
 * Structured representation of a Jira issue for use across services.
 */
readonly class JiraIssue
{
    public function __construct(
        public string  $key,
        public string  $summary,
        public string  $status,
        public ?string $assignee,
        public ?string $reporter,
        public ?string $priority,
        public ?string $issueType,
        public ?string $description,
        public ?string $sprintName,
        /** @var string[] */
        public array   $labels,
        public ?string $dueDate,
        public string  $url,
        public ?string $storyPoints,
    ) {}

    public static function fromApiResponse(array $data, string $jiraUrl): self
    {
        $fields = $data['fields'] ?? [];

        $sprintField = null;

        // Sprint is usually in a custom field — search common names
        foreach ($fields as $key => $value) {
            if (is_array($value) && isset($value[0]['name']) && str_contains($key, 'sprint')) {
                $activeSprint = collect($value)->first(fn ($s) => ($s['state'] ?? '') === 'active');
                $sprintField  = $activeSprint['name'] ?? $value[0]['name'] ?? null;
                break;
            }
        }

        return new self(
            key:         $data['key'],
            summary:     $fields['summary'] ?? '',
            status:      $fields['status']['name'] ?? 'Unknown',
            assignee:    $fields['assignee']['displayName'] ?? null,
            reporter:    $fields['reporter']['displayName'] ?? null,
            priority:    $fields['priority']['name'] ?? null,
            issueType:   $fields['issuetype']['name'] ?? null,
            description: $fields['description'] ?? null,
            sprintName:  $sprintField,
            labels:      $fields['labels'] ?? [],
            dueDate:     $fields['duedate'] ?? null,
            url:         rtrim($jiraUrl, '/') . '/browse/' . $data['key'],
            storyPoints: (string) ($fields['story_points'] ?? $fields['customfield_10016'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key'          => $this->key,
            'summary'      => $this->summary,
            'status'       => $this->status,
            'assignee'     => $this->assignee,
            'reporter'     => $this->reporter,
            'priority'     => $this->priority,
            'issue_type'   => $this->issueType,
            'sprint'       => $this->sprintName,
            'labels'       => $this->labels,
            'due_date'     => $this->dueDate,
            'url'          => $this->url,
            'story_points' => $this->storyPoints,
        ];
    }
}
