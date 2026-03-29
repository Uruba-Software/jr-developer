<?php

namespace App\Adapters;

use App\Contracts\MessagingPlatform;
use App\DTOs\IncomingMessage;
use App\Enums\MessageType;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordAdapter implements MessagingPlatform
{
    private const API_BASE = 'https://discord.com/api/v10';

    private string $botToken;
    private string $publicKey;

    public function __construct()
    {
        $this->botToken  = config('jr.platforms.discord.bot_token', '');
        $this->publicKey = config('jr.platforms.discord.public_key', '');
    }

    public function sendMessage(string $channel, string $text): void
    {
        $this->post("/channels/{$channel}/messages", ['content' => $text]);
    }

    public function sendFile(string $channel, string $path, string $name): void
    {
        try {
            Http::withToken($this->botToken, 'Bot')
                ->attach('file', file_get_contents($path), $name)
                ->post(self::API_BASE . "/channels/{$channel}/messages")
                ->throw();
        } catch (RequestException $e) {
            Log::error('DiscordAdapter [sendFile] failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function sendApprovalPrompt(string $channel, string $message, array $actions): void
    {
        $buttons = array_map(fn (array $action) => [
            'type'      => 2, // BUTTON
            'label'     => $action['label'],
            'custom_id' => $action['id'],
            'style'     => $this->mapStyle($action['style'] ?? 'default'),
        ], $actions);

        $this->post("/channels/{$channel}/messages", [
            'content'    => $message,
            'components' => [
                [
                    'type'       => 1, // ACTION_ROW
                    'components' => $buttons,
                ],
            ],
        ]);
    }

    public function parseIncoming(array $payload): IncomingMessage
    {
        // Component interaction (button click)
        if (isset($payload['type']) && $payload['type'] === 3) {
            $data = $payload['data'] ?? [];

            return new IncomingMessage(
                platform:    'discord',
                channelId:   (string) ($payload['channel_id'] ?? ''),
                userId:      (string) ($payload['member']['user']['id'] ?? $payload['user']['id'] ?? ''),
                text:        $data['custom_id'] ?? '',
                type:        MessageType::InteractiveResponse,
                rawPayload:  json_encode($payload),
                actionId:    $data['custom_id'] ?? null,
                actionValue: $data['custom_id'] ?? null,
                messageId:   (string) ($payload['message']['id'] ?? null),
            );
        }

        // Slash command (APPLICATION_COMMAND)
        if (isset($payload['type']) && $payload['type'] === 2) {
            $options = $payload['data']['options'] ?? [];
            $text    = implode(' ', array_column($options, 'value'));

            return new IncomingMessage(
                platform:   'discord',
                channelId:  (string) ($payload['channel_id'] ?? ''),
                userId:     (string) ($payload['member']['user']['id'] ?? $payload['user']['id'] ?? ''),
                text:       $text,
                type:       MessageType::Command,
                rawPayload: json_encode($payload),
                messageId:  (string) ($payload['id'] ?? null),
            );
        }

        // Regular message (MESSAGE_CREATE gateway event)
        $attachments = $payload['attachments'] ?? [];

        return new IncomingMessage(
            platform:   'discord',
            channelId:  (string) ($payload['channel_id'] ?? ''),
            userId:     (string) ($payload['author']['id'] ?? ''),
            text:       $payload['content'] ?? '',
            type:       count($attachments) > 0 ? MessageType::File : MessageType::Text,
            rawPayload: json_encode($payload),
            threadId:   isset($payload['thread']) ? (string) $payload['thread']['id'] : null,
            messageId:  (string) ($payload['id'] ?? null),
        );
    }

    public function verifyRequest(Request $request): bool
    {
        $signature = $request->header('X-Signature-Ed25519', '');
        $timestamp = $request->header('X-Signature-Timestamp', '');
        $body      = $request->getContent();

        if (empty($signature) || empty($timestamp)) {
            return false;
        }

        // Use sodium if available, otherwise fall back to a manual check for testing
        if (function_exists('sodium_crypto_sign_verify_detached')) {
            try {
                return sodium_crypto_sign_verify_detached(
                    hex2bin($signature),
                    $timestamp . $body,
                    hex2bin($this->publicKey)
                );
            } catch (\Exception) {
                return false;
            }
        }

        // Fallback: basic HMAC (not Ed25519, only for environments without libsodium)
        $expected = hash_hmac('sha256', $timestamp . $body, $this->publicKey);
        return hash_equals($expected, $signature);
    }

    private function post(string $endpoint, array $data): void
    {
        try {
            Http::withToken($this->botToken, 'Bot')
                ->post(self::API_BASE . $endpoint, $data)
                ->throw();
        } catch (RequestException $e) {
            Log::error("DiscordAdapter [{$endpoint}] failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function mapStyle(string $style): int
    {
        return match ($style) {
            'primary' => 1,
            'danger'  => 4,
            'success' => 3,
            default   => 2, // SECONDARY
        };
    }
}
