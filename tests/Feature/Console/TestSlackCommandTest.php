<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TestSlackCommandTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_succeeds_with_valid_credentials(): void
    {
        Http::fake([
            'slack.com/api/auth.test' => Http::response([
                'ok'     => true,
                'bot_id' => 'B123',
                'team'   => 'MyTeam',
            ], 200),
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'ts' => '1234567890.123456',
            ], 200),
        ]);

        $this->artisan('jr:test:slack', ['--token' => 'xoxb-valid', '--channel' => 'C123456'])
            ->expectsOutputToContain('B123')
            ->expectsOutputToContain('C123456')
            ->assertExitCode(0);
    }

    // -------------------------------------------------------------------------
    // Missing credentials
    // -------------------------------------------------------------------------

    public function test_fails_without_token(): void
    {
        config(['services.slack.bot_token' => null]);

        $this->artisan('jr:test:slack', ['--channel' => 'C123'])
            ->expectsOutputToContain('No Slack bot token')
            ->assertExitCode(1);
    }

    public function test_fails_without_channel(): void
    {
        config(['services.slack.channel_id' => null]);

        $this->artisan('jr:test:slack', ['--token' => 'xoxb-test'])
            ->expectsOutputToContain('No Slack channel')
            ->assertExitCode(1);
    }

    // -------------------------------------------------------------------------
    // Auth failure
    // -------------------------------------------------------------------------

    public function test_fails_when_auth_test_returns_not_ok(): void
    {
        Http::fake([
            'slack.com/api/auth.test' => Http::response([
                'ok'    => false,
                'error' => 'invalid_auth',
            ], 200),
        ]);

        $this->artisan('jr:test:slack', ['--token' => 'xoxb-bad', '--channel' => 'C123'])
            ->expectsOutputToContain('invalid_auth')
            ->assertExitCode(1);
    }

    // -------------------------------------------------------------------------
    // Message send failure
    // -------------------------------------------------------------------------

    public function test_fails_when_message_send_fails(): void
    {
        Http::fake([
            'slack.com/api/auth.test' => Http::response([
                'ok'     => true,
                'bot_id' => 'B123',
                'team'   => 'Team',
            ], 200),
            'slack.com/api/chat.postMessage' => Http::response([
                'ok'    => false,
                'error' => 'channel_not_found',
            ], 200),
        ]);

        $this->artisan('jr:test:slack', ['--token' => 'xoxb-valid', '--channel' => 'C_INVALID'])
            ->expectsOutputToContain('channel_not_found')
            ->assertExitCode(1);
    }
}
