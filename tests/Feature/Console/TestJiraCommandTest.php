<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TestJiraCommandTest extends TestCase
{
    private array $jiraArgs = [
        '--url'   => 'https://test.atlassian.net',
        '--user'  => 'user@test.com',
        '--token' => 'jira_api_token',
    ];

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_succeeds_with_valid_credentials(): void
    {
        Http::fake([
            'test.atlassian.net/rest/api/3/myself' => Http::response([
                'displayName'  => 'Test Developer',
                'emailAddress' => 'user@test.com',
            ], 200),
            'test.atlassian.net/rest/api/3/project/search*' => Http::response([
                'values' => [
                    ['key' => 'TEST', 'name' => 'Test Project', 'projectTypeKey' => 'software'],
                ],
            ], 200),
        ]);

        $this->artisan('jr:test:jira', $this->jiraArgs)
            ->expectsOutputToContain('Test Developer')
            ->expectsOutputToContain('TEST')
            ->assertExitCode(0);
    }

    // -------------------------------------------------------------------------
    // Missing credentials
    // -------------------------------------------------------------------------

    public function test_fails_without_credentials(): void
    {
        config([
            'services.jira.url'       => null,
            'services.jira.username'  => null,
            'services.jira.api_token' => null,
        ]);

        $this->artisan('jr:test:jira')
            ->expectsOutputToContain('not configured')
            ->assertExitCode(1);
    }

    // -------------------------------------------------------------------------
    // Auth failure
    // -------------------------------------------------------------------------

    public function test_fails_with_invalid_credentials(): void
    {
        Http::fake([
            'test.atlassian.net/rest/api/3/myself' => Http::response([], 401),
        ]);

        $this->artisan('jr:test:jira', $this->jiraArgs)
            ->expectsOutputToContain('401')
            ->assertExitCode(1);
    }
}
