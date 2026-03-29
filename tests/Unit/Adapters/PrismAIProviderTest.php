<?php

namespace Tests\Unit\Adapters;

use App\Adapters\PrismAIProvider;
use App\DTOs\AIResponse;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

class PrismAIProviderTest extends TestCase
{
    private PrismAIProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jr.ai.default_provider' => 'anthropic',
            'jr.ai.default_model'    => 'claude-haiku-4-5-20251001',
        ]);

        $this->provider = new PrismAIProvider();
    }

    private function fakeResponse(string $text, int $input = 100, int $output = 50): void
    {
        Prism::fake([
            TextResponseFake::make()
                ->withText($text)
                ->withUsage(new Usage($input, $output)),
        ]);
    }

    // -------------------------------------------------------------------------
    // complete
    // -------------------------------------------------------------------------

    public function test_complete_returns_ai_response(): void
    {
        $this->fakeResponse('Hello from AI');

        $response = $this->provider->complete([
            ['role' => 'user', 'content' => 'Say hello'],
        ]);

        $this->assertInstanceOf(AIResponse::class, $response);
        $this->assertSame('Hello from AI', $response->content);
    }

    public function test_complete_maps_all_message_roles(): void
    {
        $this->fakeResponse('Response');

        $response = $this->provider->complete([
            ['role' => 'system',    'content' => 'You are a helpful assistant'],
            ['role' => 'user',      'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there'],
            ['role' => 'user',      'content' => 'How are you?'],
        ]);

        $this->assertInstanceOf(AIResponse::class, $response);
    }

    public function test_complete_returns_token_counts(): void
    {
        $this->fakeResponse('Response', 100, 50);

        $response = $this->provider->complete([
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        $this->assertSame(100, $response->inputTokens);
        $this->assertSame(50, $response->outputTokens);
        $this->assertSame(150, $response->totalTokens());
    }

    public function test_complete_with_empty_messages(): void
    {
        $this->fakeResponse('ok');

        $response = $this->provider->complete([]);

        $this->assertInstanceOf(AIResponse::class, $response);
    }

    public function test_complete_sets_stop_reason(): void
    {
        $this->fakeResponse('done');

        $response = $this->provider->complete([['role' => 'user', 'content' => 'hi']]);

        $this->assertNotNull($response->stopReason);
    }

    // -------------------------------------------------------------------------
    // withModel / forTier
    // -------------------------------------------------------------------------

    public function test_with_model_returns_new_instance(): void
    {
        $cloned = $this->provider->withModel('openai', 'gpt-4o');

        $this->assertNotSame($this->provider, $cloned);
        $this->assertInstanceOf(PrismAIProvider::class, $cloned);
    }

    public function test_for_tier_fast_returns_new_instance(): void
    {
        $fast = $this->provider->forTier('fast');

        $this->assertInstanceOf(PrismAIProvider::class, $fast);
        $this->assertNotSame($this->provider, $fast);
    }

    public function test_for_tier_smart_returns_new_instance(): void
    {
        $smart = $this->provider->forTier('smart');

        $this->assertInstanceOf(PrismAIProvider::class, $smart);
        $this->assertNotSame($this->provider, $smart);
    }

    public function test_for_tier_unknown_falls_back_to_fast(): void
    {
        $unknown = $this->provider->forTier('unknown_tier');

        $this->assertInstanceOf(PrismAIProvider::class, $unknown);
    }

    public function test_with_model_does_not_mutate_original(): void
    {
        $original = new PrismAIProvider();
        $original->withModel('openai', 'gpt-4o');

        $this->fakeResponse('original response');
        $response = $original->complete([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('original response', $response->content);
    }

    // -------------------------------------------------------------------------
    // tools
    // -------------------------------------------------------------------------

    public function test_complete_with_string_tool_parameter(): void
    {
        $this->fakeResponse('I will read the file');

        $response = $this->provider->complete(
            messages: [['role' => 'user', 'content' => 'Read app.php']],
            tools: [
                [
                    'name'        => 'read_file',
                    'description' => 'Read a file',
                    'parameters'  => [
                        'properties' => [
                            'path' => ['type' => 'string', 'description' => 'File path'],
                        ],
                        'required' => ['path'],
                    ],
                ],
            ],
        );

        $this->assertInstanceOf(AIResponse::class, $response);
    }

    public function test_complete_with_number_and_boolean_tool_params(): void
    {
        $this->fakeResponse('Done');

        $response = $this->provider->complete(
            messages: [['role' => 'user', 'content' => 'Run tests']],
            tools: [
                [
                    'name'        => 'run_tests',
                    'description' => 'Run test suite',
                    'parameters'  => [
                        'properties' => [
                            'timeout' => ['type' => 'integer', 'description' => 'Timeout in seconds'],
                            'verbose' => ['type' => 'boolean', 'description' => 'Verbose output'],
                        ],
                        'required' => ['timeout'],
                    ],
                ],
            ],
        );

        $this->assertInstanceOf(AIResponse::class, $response);
    }

    public function test_has_no_tool_calls_when_none_returned(): void
    {
        $this->fakeResponse('Plain text response');

        $response = $this->provider->complete([['role' => 'user', 'content' => 'hi']]);

        $this->assertFalse($response->hasToolCalls());
        $this->assertSame([], $response->toolCalls);
    }
}
