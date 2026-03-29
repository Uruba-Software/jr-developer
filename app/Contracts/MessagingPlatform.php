<?php

namespace App\Contracts;

use App\DTOs\IncomingMessage;
use Illuminate\Http\Request;

interface MessagingPlatform
{
    /**
     * Send a plain text message to a channel.
     */
    public function sendMessage(string $channel, string $text): void;

    /**
     * Upload a file to a channel.
     */
    public function sendFile(string $channel, string $path, string $name): void;

    /**
     * Send an interactive message with action buttons (e.g. approve/reject diff).
     *
     * @param  array<array{id: string, label: string, style?: string}>  $actions
     */
    public function sendApprovalPrompt(string $channel, string $message, array $actions): void;

    /**
     * Parse an incoming webhook payload into a normalized IncomingMessage.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseIncoming(array $payload): IncomingMessage;

    /**
     * Verify that the incoming request is authentically from the platform.
     */
    public function verifyRequest(Request $request): bool;
}
