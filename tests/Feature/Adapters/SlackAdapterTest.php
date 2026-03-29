<?php

namespace Tests\Feature\Adapters;

use App\Adapters\SlackAdapter;
use App\Enums\MessageType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackAdapterTest extends TestCase
{
    private SlackAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jr.platforms.slack.bot_token'      => 'xoxb-test-token',
            'jr.platforms.slack.signing_secret'  => 'test-signing-secret',
        ]);

        $this->adapter = new SlackAdapter();
    }

    // -------------------------------------------------------------------------
    // sendMessage
    // -------------------------------------------------------------------------

    public function test_send_message_posts_to_slack_api(): void
    {
        Http::fake(['slack.com/*' => Http::response(['ok' => true], 200)]);

        $this->adapter->sendMessage('C12345', 'Hello world');

        Http::assertSent(fn ($req) =>
            str_contains($req->url(), 'chat.postMessage') &&
            $req['channel'] === 'C12345' &&
            $req['text'] === 'Hello world'
        );
    }

    // -------------------------------------------------------------------------
    // sendApprovalPrompt
    // -------------------------------------------------------------------------

    public function test_send_approval_prompt_sends_block_kit_message(): void
    {
        Http::fake(['slack.com/*' => Http::response(['ok' => true], 200)]);

        $this->adapter->sendApprovalPrompt('C12345', 'Approve diff?', [
            ['id' => 'approve', 'label' => 'Approve', 'style' => 'primary'],
            ['id' => 'reject',  'label' => 'Reject',  'style' => 'danger'],
        ]);

        Http::assertSent(function ($req) {
            $blocks = $req['blocks'];
            $buttons = $blocks[1]['elements'];

            return str_contains($req->url(), 'chat.postMessage')
                && $req['channel'] === 'C12345'
                && count($buttons) === 2
                && $buttons[0]['value'] === 'approve'
                && $buttons[1]['value'] === 'reject';
        });
    }

    // -------------------------------------------------------------------------
    // parseIncoming — text message
    // -------------------------------------------------------------------------

    public function test_parse_incoming_text_message(): void
    {
        $payload = [
            'event' => [
                'type'    => 'message',
                'channel' => 'C12345',
                'user'    => 'U67890',
                'text'    => 'Hello bot',
                'ts'      => '1234567890.000001',
            ],
        ];

        $msg = $this->adapter->parseIncoming($payload);

        $this->assertSame('slack', $msg->platform);
        $this->assertSame('C12345', $msg->channelId);
        $this->assertSame('U67890', $msg->userId);
        $this->assertSame('Hello bot', $msg->text);
        $this->assertSame(MessageType::Text, $msg->type);
        $this->assertSame('1234567890.000001', $msg->messageId);
    }

    public function test_parse_incoming_threaded_message(): void
    {
        $payload = [
            'event' => [
                'channel'   => 'C12345',
                'user'      => 'U67890',
                'text'      => 'reply',
                'ts'        => '1234567890.000002',
                'thread_ts' => '1234567890.000001',
            ],
        ];

        $msg = $this->adapter->parseIncoming($payload);

        $this->assertSame('1234567890.000001', $msg->threadId);
    }

    public function test_parse_incoming_file_message(): void
    {
        $payload = [
            'event' => [
                'channel' => 'C12345',
                'user'    => 'U67890',
                'text'    => '',
                'files'   => [['id' => 'F12345']],
            ],
        ];

        $msg = $this->adapter->parseIncoming($payload);

        $this->assertSame(MessageType::File, $msg->type);
    }

    // -------------------------------------------------------------------------
    // parseIncoming — interactive (button click)
    // -------------------------------------------------------------------------

    public function test_parse_incoming_interactive_button_click(): void
    {
        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U67890'],
            'container' => [
                'channel_id' => 'C12345',
                'message_ts' => '1234567890.000001',
            ],
            'actions' => [
                [
                    'action_id' => 'approve_diff',
                    'value'     => 'yes',
                ],
            ],
        ];

        $msg = $this->adapter->parseIncoming($payload);

        $this->assertSame(MessageType::InteractiveResponse, $msg->type);
        $this->assertSame('approve_diff', $msg->actionId);
        $this->assertSame('yes', $msg->actionValue);
        $this->assertTrue($msg->isInteractive());
    }

    // -------------------------------------------------------------------------
    // parseIncoming — slash command
    // -------------------------------------------------------------------------

    public function test_parse_incoming_slash_command(): void
    {
        $payload = [
            'command'    => '/jr',
            'channel_id' => 'C12345',
            'user_id'    => 'U67890',
            'text'       => 'status',
            'trigger_id' => 'T99999',
        ];

        $msg = $this->adapter->parseIncoming($payload);

        $this->assertSame(MessageType::Command, $msg->type);
        $this->assertSame('status', $msg->text);
        $this->assertSame('T99999', $msg->messageId);
        $this->assertTrue($msg->isCommand());
    }

    // -------------------------------------------------------------------------
    // verifyRequest
    // -------------------------------------------------------------------------

    public function test_verify_request_passes_with_valid_signature(): void
    {
        $timestamp = (string) time();
        $body      = '{"type":"event_callback"}';
        $secret    = 'test-signing-secret';
        $sig       = 'v0=' . hash_hmac('sha256', "v0:{$timestamp}:{$body}", $secret);

        $request = Request::create('/', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Slack-Request-Timestamp', $timestamp);
        $request->headers->set('X-Slack-Signature', $sig);

        $this->assertTrue($this->adapter->verifyRequest($request));
    }

    public function test_verify_request_fails_with_invalid_signature(): void
    {
        $timestamp = (string) time();
        $body      = '{"type":"event_callback"}';

        $request = Request::create('/', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Slack-Request-Timestamp', $timestamp);
        $request->headers->set('X-Slack-Signature', 'v0=invalidsignature');

        $this->assertFalse($this->adapter->verifyRequest($request));
    }

    public function test_verify_request_fails_with_stale_timestamp(): void
    {
        $timestamp = (string) (time() - 400); // older than 5 min
        $body      = '{}';
        $secret    = 'test-signing-secret';
        $sig       = 'v0=' . hash_hmac('sha256', "v0:{$timestamp}:{$body}", $secret);

        $request = Request::create('/', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Slack-Request-Timestamp', $timestamp);
        $request->headers->set('X-Slack-Signature', $sig);

        $this->assertFalse($this->adapter->verifyRequest($request));
    }

    // -------------------------------------------------------------------------
    // sendFile
    // -------------------------------------------------------------------------

    public function test_send_file_uploads_to_slack(): void
    {
        Http::fake(['slack.com/*' => Http::response(['ok' => true], 200)]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'jr_test_');
        file_put_contents($tmpFile, 'diff content');

        $this->adapter->sendFile('C12345', $tmpFile, 'diff.txt');

        Http::assertSent(fn ($req) => str_contains($req->url(), 'files.upload'));

        unlink($tmpFile);
    }
}
