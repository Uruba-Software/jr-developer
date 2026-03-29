<?php

namespace Tests\Unit\Services;

use App\Contracts\AIProvider;
use App\Contracts\MessagingPlatform;
use App\Contracts\ToolRunner;
use App\DTOs\AIResponse;
use App\DTOs\IncomingMessage;
use App\DTOs\ToolResult;
use App\Enums\MessageType;
use App\Enums\OperatingMode;
use App\Enums\ToolPermission;
use App\Services\ConversationContextManager;
use App\Services\MessageDispatcher;
use App\Services\MessageProcessor;
use App\Services\ToolExecutor;
use App\Services\ToolRegistry;
use Mockery;
use Tests\TestCase;

class MessageProcessorTest extends TestCase
{
    private MessagingPlatform          $platform;
    private AIProvider                 $ai;
    private ConversationContextManager $context;
    private ToolRegistry               $registry;
    private ToolExecutor               $executor;
    private MessageDispatcher          $dispatcher;
    private MessageProcessor           $processor;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        $this->platform   = Mockery::mock(MessagingPlatform::class);
        $this->ai         = Mockery::mock(AIProvider::class);
        $this->registry   = new ToolRegistry();
        $this->executor   = new ToolExecutor($this->registry);
        $this->context    = new ConversationContextManager();
        $this->dispatcher = new MessageDispatcher($this->platform);

        $this->processor = new MessageProcessor(
            $this->context,
            $this->ai,
            $this->executor,
            $this->platform,
            $this->dispatcher,
        );
    }

    private function makeMessage(string $text = 'Hello', string $channel = 'C123'): IncomingMessage
    {
        return new IncomingMessage(
            platform:   'slack',
            channelId:  $channel,
            userId:     'U123',
            text:       $text,
            type:       MessageType::Text,
            rawPayload: '{}',
        );
    }

    private function aiResponse(string $content, array $toolCalls = []): AIResponse
    {
        return new AIResponse(
            content:      $content,
            inputTokens:  10,
            outputTokens: 20,
            toolCalls:    $toolCalls,
        );
    }

    // -------------------------------------------------------------------------
    // Manual mode
    // -------------------------------------------------------------------------

    public function test_manual_mode_sends_disabled_message(): void
    {
        $this->platform->shouldReceive('sendMessage')
            ->once()
            ->with('C123', Mockery::on(fn ($msg) => str_contains($msg, 'Manual mode')));

        $this->processor->handle($this->makeMessage(), OperatingMode::Manual);
    }

    public function test_manual_mode_does_not_call_ai(): void
    {
        $this->platform->shouldReceive('sendMessage')->once();
        $this->ai->shouldNotReceive('complete');

        $this->processor->handle($this->makeMessage(), OperatingMode::Manual);
    }

    // -------------------------------------------------------------------------
    // Agent mode — happy path
    // -------------------------------------------------------------------------

    public function test_agent_mode_sends_ai_response(): void
    {
        $this->ai->shouldReceive('complete')
            ->once()
            ->andReturn($this->aiResponse('AI says hello'));

        $this->platform->shouldReceive('sendMessage')
            ->once()
            ->with('C123', 'AI says hello');

        $this->processor->handle($this->makeMessage(), OperatingMode::Agent);
    }

    public function test_cloud_mode_also_uses_agent_flow(): void
    {
        $this->ai->shouldReceive('complete')
            ->once()
            ->andReturn($this->aiResponse('Cloud response'));

        $this->platform->shouldReceive('sendMessage')->once();

        $this->processor->handle($this->makeMessage(), OperatingMode::Cloud);
    }

    public function test_agent_mode_appends_messages_to_context(): void
    {
        $this->ai->shouldReceive('complete')
            ->once()
            ->andReturn($this->aiResponse('Response'));

        $this->platform->shouldReceive('sendMessage')->once();

        $msg = $this->makeMessage('Tell me something');
        $this->processor->handle($msg, OperatingMode::Agent);

        $context = $this->context->get('slack:C123');

        $this->assertCount(2, $context); // user + assistant
        $this->assertSame('user',      $context[0]['role']);
        $this->assertSame('assistant', $context[1]['role']);
        $this->assertSame('Tell me something', $context[0]['content']);
    }

    // -------------------------------------------------------------------------
    // Agent mode — tool call flow
    // -------------------------------------------------------------------------

    public function test_agent_mode_executes_read_tool_and_continues(): void
    {
        // Register a read tool
        $this->registry->register(new class implements ToolRunner {
            const NAME = 'read_file';
            public function supports(string $tool): bool { return $tool === 'read_file'; }
            public function run(string $tool, array $params): ToolResult { return ToolResult::ok('file content'); }
            public function permission(): ToolPermission { return ToolPermission::Read; }
        });

        // First call: returns tool call. Second: returns final response.
        $this->ai->shouldReceive('complete')
            ->twice()
            ->andReturn(
                $this->aiResponse('', [['name' => 'read_file', 'arguments' => ['path' => 'app.php']]]),
                $this->aiResponse('Here is the file content'),
            );

        $this->platform->shouldReceive('sendMessage')
            ->once()
            ->with('C123', 'Here is the file content');

        $this->processor->handle($this->makeMessage(), OperatingMode::Agent);
    }

    public function test_agent_mode_sends_approval_prompt_for_write_tool(): void
    {
        $this->registry->register(new class implements ToolRunner {
            const NAME = 'write_file';
            public function supports(string $tool): bool { return $tool === 'write_file'; }
            public function run(string $tool, array $params): ToolResult { return ToolResult::ok('done'); }
            public function permission(): ToolPermission { return ToolPermission::Write; }
        });

        // AI wants to call write_file, then gives up (no more tool calls)
        $this->ai->shouldReceive('complete')
            ->twice()
            ->andReturn(
                $this->aiResponse('', [['name' => 'write_file', 'arguments' => []]]),
                $this->aiResponse('Could not write file'),
            );

        $this->platform->shouldReceive('sendApprovalPrompt')
            ->once()
            ->with('C123', Mockery::type('string'), Mockery::type('array'));

        $this->platform->shouldReceive('sendMessage')->once();

        $this->processor->handle($this->makeMessage(), OperatingMode::Agent);
    }

    // -------------------------------------------------------------------------
    // Max tool rounds
    // -------------------------------------------------------------------------

    public function test_agent_mode_stops_after_max_tool_rounds(): void
    {
        // Register auto-approved tool so it runs without prompts
        $this->registry->register(new class implements ToolRunner {
            const NAME = 'read_file';
            public function supports(string $tool): bool { return $tool === 'read_file'; }
            public function run(string $tool, array $params): ToolResult { return ToolResult::ok('content'); }
            public function permission(): ToolPermission { return ToolPermission::Read; }
        });

        // AI always returns tool calls → hits limit
        $this->ai->shouldReceive('complete')
            ->times(5)
            ->andReturn($this->aiResponse('', [['name' => 'read_file', 'arguments' => []]]));

        $this->platform->shouldReceive('sendMessage')
            ->once()
            ->with('C123', Mockery::on(fn ($m) => str_contains($m, 'max tool call limit')));

        $this->processor->handle($this->makeMessage(), OperatingMode::Agent);
    }

    // -------------------------------------------------------------------------
    // Conversation isolation
    // -------------------------------------------------------------------------

    public function test_different_channels_have_separate_contexts(): void
    {
        $this->ai->shouldReceive('complete')
            ->twice()
            ->andReturn($this->aiResponse('ok'));

        $this->platform->shouldReceive('sendMessage')->twice();

        $this->processor->handle($this->makeMessage('Hi', 'C-AAA'), OperatingMode::Agent);
        $this->processor->handle($this->makeMessage('Hi', 'C-BBB'), OperatingMode::Agent);

        $this->assertCount(2, $this->context->get('slack:C-AAA'));
        $this->assertCount(2, $this->context->get('slack:C-BBB'));
    }
}
