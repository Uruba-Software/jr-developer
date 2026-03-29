<?php

namespace App\Console\Commands\Setup;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * T17 — php artisan jr:test:slack
 *
 * Sends a test message to the configured Slack channel to verify the bot token.
 */
class TestSlackCommand extends Command
{
    protected $signature = 'jr:test:slack
        {--token= : Slack bot token (overrides config)}
        {--channel= : Slack channel ID (overrides config)}';

    protected $description = 'Test Slack bot connection by sending a test message';

    public function handle(): int
    {
        $token   = $this->option('token')   ?? config('services.slack.bot_token');
        $channel = $this->option('channel') ?? config('services.slack.channel_id');

        if (!$token) {
            $this->error('No Slack bot token configured. Set SLACK_BOT_TOKEN in .env or use --token option.');

            return self::FAILURE;
        }

        if (!$channel) {
            $this->error('No Slack channel configured. Set SLACK_CHANNEL_ID in .env or use --channel option.');

            return self::FAILURE;
        }

        $this->info('Testing Slack connection...');

        // Verify token via auth.test
        $authResponse = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json',
        ])->post('https://slack.com/api/auth.test');

        $authData = $authResponse->json();

        if (!($authData['ok'] ?? false)) {
            $this->error("Slack auth failed: " . ($authData['error'] ?? 'unknown'));

            return self::FAILURE;
        }

        $this->info("✓ Authenticated as bot: <comment>{$authData['bot_id']}</comment> in team <comment>{$authData['team']}</comment>");

        // Send test message
        $msgResponse = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json',
        ])->post('https://slack.com/api/chat.postMessage', [
            'channel' => $channel,
            'text'    => ':white_check_mark: jr-developer connection test — this is a test message.',
        ]);

        $msgData = $msgResponse->json();

        if (!($msgData['ok'] ?? false)) {
            $this->error("Could not send message: " . ($msgData['error'] ?? 'unknown'));
            $this->warn('Hint: Ensure the bot is invited to the channel (e.g. /invite @jr-developer).');

            return self::FAILURE;
        }

        $this->info("✓ Test message sent to channel <comment>{$channel}</comment> successfully.");

        return self::SUCCESS;
    }
}
