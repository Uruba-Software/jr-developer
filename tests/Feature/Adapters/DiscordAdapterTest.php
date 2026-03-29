<?php

namespace Tests\Feature\Adapters;

use App\Adapters\DiscordAdapter;
use App\Enums\MessageType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordAdapterTest extends TestCase
{
    private DiscordAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jr.platforms.discord.bot_token'  => 'test-bot-token',
            'jr.platforms.discord.public_key'  => str_repeat('a', 64), // 32 bytes hex
        ]);

        $this->adapter = new DiscordAdapter();
    }

    // -------------------------------------------------------------------------
    // sendMessage
    // -------------------------------------------------------------------------

    public function test_send_message_posts_to_discord_api(): void
    {
        Http::fake(['discord.com/*' => Http::response([], 200)]);

        $this->adapter->sendMessage('111222333', 'Hello Discord');

        Http::assertSent(fn ($req) =>
            str_contains($req->url(), '/channels/111222333/messages') &&
            $req['content'] === 'Hello Discord'
        );
    }

    // -------------------------------------------------------------------------
    // sendApprovalPrompt
    // -------------------------------------------------------------------------

    public function test_send_approval_prompt_sends_components(): void
    {
        Http::fake(['discord.com/*' => Http::response([], 200)]);

        $this->adapter->sendApprovalPrompt('111222333', 'Approve?', [
            ['id' => 'approve', 'label' => 'Approve', 'style' => 'primary'],
            ['id' => 'reject',  'label' => 'Reject',  'style' => 'danger'],
        ]);

        Http::assertSent(function ($req) {
            $components = $req['components'][0]['components'] ?? [];

            return str_contains($req->url(), '/channels/111222333/messages')
                && count($components) === 2
                && $components[0]['custom_id'] === 'approve'
                && $components[0]['style'] === 1   // primary
                && $components[1]['style'] === 4;  // danger
        });
    }

    // -------------------------------------------------------------------------
    // parseIncoming — text message
    // -------------------------------------------------------------------------

    public function test_parse_incoming_text_message(): void
    {
        $payload = [
            'id'         => '999888777',
            'channel_id' => '111222333',
            'author'     => ['id' => '444555666'],
            'content'    => 'Hello bot',
            'attachments' => [],
        ];

        $msg = $this->adapter->parseIncoming($payload);

        $this->assertSame('discord', $msg->platform);
        $this->assertSame('111222333', $msg->channelId);
        $this->assertSame('444555666', $msg->userId);
        $this->assertSame('Hello bot', $msg->text);
        $this->assertSame(MessageType::Text, $msg->type);
        $this->assertSame('999888777', $msg->messageId);
    }

    public function test_parse_incoming_file_message(): void
    {
        $payload = [
            'id'          => '999888777',
            'channel_id'  => '111222333',
            'author'      => ['id' => '444555666'],
            'content'     => '',
            'attachments' => [['id' => 'A123', 'url' => 'https://cdn.discordapp.com/file.txt']],
        ];

        $msg = $this->adapter->parseIncoming($payload);

        $this->assertSame(MessageType::File, $msg->type);
    }

    public function test_parse_incoming_threaded_message(): void
    {
        $payload = [
            'id'          => '999888777',
            'channel_id'  => '111222333',
            'author'      => ['id' => '444555666'],
            'content'     => 'reply',
            'attachments' => [],
            'thread'      => ['id' => '777666555'],
        ];

        $msg = $this->adapter->parseIncoming($payload);

        $this->assertSame('777666555', $msg->threadId);
    }

    // -------------------------------------------------------------------------
    // parseIncoming — button click (type 3)
    // -------------------------------------------------------------------------

    public function test_parse_incoming_button_click(): void
    {
        $payload = [
            'type'       => 3,
            'id'         => 'I12345',
            'channel_id' => '111222333',
            'member'     => ['user' => ['id' => '444555666']],
            'data'       => ['custom_id' => 'approve_diff'],
            'message'    => ['id' => '999888777'],
        ];

        $msg = $this->adapter->parseIncoming($payload);

        $this->assertSame(MessageType::InteractiveResponse, $msg->type);
        $this->assertSame('approve_diff', $msg->actionId);
        $this->assertTrue($msg->isInteractive());
    }

    // -------------------------------------------------------------------------
    // parseIncoming — slash command (type 2)
    // -------------------------------------------------------------------------

    public function test_parse_incoming_slash_command(): void
    {
        $payload = [
            'type'       => 2,
            'id'         => 'I12345',
            'channel_id' => '111222333',
            'member'     => ['user' => ['id' => '444555666']],
            'data'       => [
                'name'    => 'jr',
                'options' => [['name' => 'action', 'value' => 'status']],
            ],
        ];

        $msg = $this->adapter->parseIncoming($payload);

        $this->assertSame(MessageType::Command, $msg->type);
        $this->assertSame('status', $msg->text);
        $this->assertTrue($msg->isCommand());
    }

    // -------------------------------------------------------------------------
    // verifyRequest
    // -------------------------------------------------------------------------

    public function test_verify_request_fails_with_missing_headers(): void
    {
        $request = Request::create('/', 'POST', [], [], [], [], '{}');

        $this->assertFalse($this->adapter->verifyRequest($request));
    }

    public function test_verify_request_fails_with_wrong_signature(): void
    {
        $request = Request::create('/', 'POST', [], [], [], [], '{}');
        $request->headers->set('X-Signature-Ed25519', 'invalidsig');
        $request->headers->set('X-Signature-Timestamp', (string) time());

        $this->assertFalse($this->adapter->verifyRequest($request));
    }

    // -------------------------------------------------------------------------
    // sendFile
    // -------------------------------------------------------------------------

    public function test_send_file_uploads_to_discord(): void
    {
        Http::fake(['discord.com/*' => Http::response([], 200)]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'jr_discord_');
        file_put_contents($tmpFile, 'file content');

        $this->adapter->sendFile('111222333', $tmpFile, 'output.txt');

        Http::assertSent(fn ($req) =>
            str_contains($req->url(), '/channels/111222333/messages')
        );

        unlink($tmpFile);
    }
}
