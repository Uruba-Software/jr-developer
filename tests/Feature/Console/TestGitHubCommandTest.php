<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TestGitHubCommandTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_succeeds_with_valid_token(): void
    {
        Http::fake([
            'api.github.com/user' => Http::response([
                'login' => 'dev-user',
                'name'  => 'Dev User',
            ], 200),
            'api.github.com/user/repos*' => Http::response([
                [
                    'full_name'      => 'dev-user/my-repo',
                    'default_branch' => 'main',
                    'private'        => false,
                    'pushed_at'      => '2026-01-15T10:00:00Z',
                ],
            ], 200),
        ]);

        $this->artisan('jr:test:github', ['--token' => 'ghp_valid'])
            ->expectsOutputToContain('dev-user')
            ->assertExitCode(0);
    }

    // -------------------------------------------------------------------------
    // Invalid / missing input
    // -------------------------------------------------------------------------

    public function test_fails_without_token(): void
    {
        config(['services.github.token' => null]);

        $this->artisan('jr:test:github')
            ->expectsOutputToContain('No GitHub token')
            ->assertExitCode(1);
    }

    public function test_fails_with_invalid_token(): void
    {
        Http::fake([
            'api.github.com/user' => Http::response(['message' => 'Bad credentials'], 401),
        ]);

        $this->artisan('jr:test:github', ['--token' => 'bad_token'])
            ->expectsOutputToContain('401')
            ->assertExitCode(1);
    }
}
