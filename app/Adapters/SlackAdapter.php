<?php

namespace App\Adapters;

use App\Contracts\MessagingPlatform;
use App\DTOs\IncomingMessage;
use App\Enums\MessageType;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackAdapter implements MessagingPlatform
{
    private string $botToken;
    private string $signingSecret;

    public function __construct()
    {
        $this->botToken      = config('jr.platforms.slack.bot_token') ?? '';
        $this->signingSecret = config('jr.platforms.slack.signing_secret') ?? '';
    }

    public function sendMessage(string $channel, string $text): void
    {
        $this->post('chat.postMessage', [
            'channel' => $channel,
            'text'    => $text,
        ]);
    }

    public function sendFile(string $channel, string $path, string $name): void
    {
        Http::withToken($this->botToken)
            ->attach('file', file_get_contents($path), $name)
            ->post('https://slack.com/api/files.upload', [
                'channels' => $channel,
                'filename' => $name,
            ])
            ->throw();
    }

    public function sendApprovalPrompt(string $channel, string $message, array $actions): void
    {
        $buttons = array_map(fn (array $action) => [
            'type'  => 'button',
            'text'  => ['type' => 'plain_text', 'text' => $action['label']],
            'value' => $action['id'],
            'style' => $action['style'] ?? 'default',
        ], $actions);

        $this->post('chat.postMessage', [
            'channel' => $channel,
            'text'    => $message,
            'blocks'  => [
                [
                    'type' => 'section',
                    'text' => ['type' => 'mrkdwn', 'text' => $message],
                ],
                [
                    'type'     => 'actions',
                    'elements' => $buttons,
                ],
            ],
        ]);
    }

    public function parseIncoming(array $payload): IncomingMessage
    {
        // Interactive payload (button click)
        if (isset($payload['type']) && $payload['type'] === 'block_actions') {
            $action = $payload['actions'][0] ?? [];

            return new IncomingMessage(
                platform:    'slack',
                channelId:   $payload['channel']['id'] ?? $payload['container']['channel_id'] ?? '',
                userId:      $payload['user']['id'] ?? '',
                text:        $action['value'] ?? '',
                type:        MessageType::InteractiveResponse,
                rawPayload:  json_encode($payload),
                actionId:    $action['action_id'] ?? null,
                actionValue: $action['value'] ?? null,
                threadId:    $payload['container']['thread_ts'] ?? null,
                messageId:   $payload['container']['message_ts'] ?? null,
            );
        }

        // Slash command
        if (isset($payload['command'])) {
            return new IncomingMessage(
                platform:   'slack',
                channelId:  $payload['channel_id'] ?? '',
                userId:     $payload['user_id'] ?? '',
                text:       $payload['text'] ?? '',
                type:       MessageType::Command,
                rawPayload: json_encode($payload),
                messageId:  $payload['trigger_id'] ?? null,
            );
        }

        // Regular message event
        $event = $payload['event'] ?? $payload;

        return new IncomingMessage(
            platform:   'slack',
            channelId:  $event['channel'] ?? '',
            userId:     $event['user'] ?? '',
            text:       $event['text'] ?? '',
            type:       isset($event['files']) ? MessageType::File : MessageType::Text,
            rawPayload: json_encode($payload),
            threadId:   $event['thread_ts'] ?? null,
            messageId:  $event['ts'] ?? null,
        );
    }

    public function verifyRequest(Request $request): bool
    {
        $timestamp = $request->header('X-Slack-Request-Timestamp', '');
        $signature = $request->header('X-Slack-Signature', '');

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $baseString    = "v0:{$timestamp}:" . $request->getContent();
        $expected      = 'v0=' . hash_hmac('sha256', $baseString, $this->signingSecret);

        return hash_equals($expected, $signature);
    }

    private function post(string $method, array $data): void
    {
        try {
            Http::withToken($this->botToken)
                ->post("https://slack.com/api/{$method}", $data)
                ->throw();
        } catch (RequestException $e) {
            Log::error("SlackAdapter [{$method}] failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
