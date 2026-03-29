<?php

namespace Tests\Unit\DTOs;

use App\DTOs\IncomingMessage;
use App\Enums\MessagePlatform;
use App\Enums\MessageType;
use Tests\TestCase;

class IncomingMessageTest extends TestCase
{
    private function makeMessage(MessageType $type = MessageType::Text, array $overrides = []): IncomingMessage
    {
        return new IncomingMessage(
            platform:   $overrides['platform'] ?? 'slack',
            channelId:  $overrides['channelId'] ?? 'C12345',
            userId:     $overrides['userId'] ?? 'U67890',
            text:       $overrides['text'] ?? 'Hello',
            type:       $type,
            rawPayload: $overrides['rawPayload'] ?? '{}',
        );
    }

    public function test_it_creates_with_required_fields(): void
    {
        $msg = $this->makeMessage();

        $this->assertSame('slack', $msg->platform);
        $this->assertSame('C12345', $msg->channelId);
        $this->assertSame('U67890', $msg->userId);
        $this->assertSame('Hello', $msg->text);
        $this->assertSame(MessageType::Text, $msg->type);
        $this->assertNull($msg->actionId);
        $this->assertNull($msg->actionValue);
        $this->assertNull($msg->threadId);
        $this->assertNull($msg->messageId);
    }

    public function test_it_creates_with_optional_fields(): void
    {
        $msg = new IncomingMessage(
            platform:    'slack',
            channelId:   'C12345',
            userId:      'U67890',
            text:        'approved',
            type:        MessageType::InteractiveResponse,
            rawPayload:  '{}',
            actionId:    'approve_diff',
            actionValue: 'yes',
            threadId:    'T99999',
            messageId:   'M11111',
        );

        $this->assertSame('approve_diff', $msg->actionId);
        $this->assertSame('yes', $msg->actionValue);
        $this->assertSame('T99999', $msg->threadId);
        $this->assertSame('M11111', $msg->messageId);
    }

    public function test_is_interactive_returns_true_for_interactive_type(): void
    {
        $msg = $this->makeMessage(MessageType::InteractiveResponse);

        $this->assertTrue($msg->isInteractive());
        $this->assertFalse($msg->isCommand());
    }

    public function test_is_command_returns_true_for_command_type(): void
    {
        $msg = $this->makeMessage(MessageType::Command);

        $this->assertTrue($msg->isCommand());
        $this->assertFalse($msg->isInteractive());
    }

    public function test_is_interactive_returns_false_for_text_type(): void
    {
        $msg = $this->makeMessage(MessageType::Text);

        $this->assertFalse($msg->isInteractive());
        $this->assertFalse($msg->isCommand());
    }

    public function test_get_platform_enum_returns_correct_enum(): void
    {
        $msg = $this->makeMessage(overrides: ['platform' => 'slack']);

        $this->assertSame(MessagePlatform::Slack, $msg->getPlatformEnum());
    }

    public function test_get_platform_enum_returns_discord(): void
    {
        $msg = $this->makeMessage(overrides: ['platform' => 'discord']);

        $this->assertSame(MessagePlatform::Discord, $msg->getPlatformEnum());
    }

    public function test_get_platform_enum_throws_for_unknown_platform(): void
    {
        $msg = $this->makeMessage(overrides: ['platform' => 'unknown']);

        $this->expectException(\ValueError::class);
        $msg->getPlatformEnum();
    }
}
