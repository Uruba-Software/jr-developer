<?php

namespace App\DTOs;

use App\Enums\MessagePlatform;
use App\Enums\MessageType;

readonly class IncomingMessage
{
    public function __construct(
        public string          $platform,
        public string          $channelId,
        public string          $userId,
        public string          $text,
        public MessageType     $type,
        public string          $rawPayload,
        public ?string         $actionId = null,
        public ?string         $actionValue = null,
        public ?string         $threadId = null,
        public ?string         $messageId = null,
    ) {}

    public function isInteractive(): bool
    {
        return $this->type === MessageType::InteractiveResponse;
    }

    public function isCommand(): bool
    {
        return $this->type === MessageType::Command;
    }

    public function getPlatformEnum(): MessagePlatform
    {
        return MessagePlatform::from($this->platform);
    }
}
