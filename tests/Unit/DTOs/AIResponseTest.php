<?php

namespace Tests\Unit\DTOs;

use App\DTOs\AIResponse;
use Tests\TestCase;

class AIResponseTest extends TestCase
{
    public function test_it_creates_with_required_fields(): void
    {
        $response = new AIResponse(
            content:      'Hello world',
            inputTokens:  100,
            outputTokens: 50,
        );

        $this->assertSame('Hello world', $response->content);
        $this->assertSame(100, $response->inputTokens);
        $this->assertSame(50, $response->outputTokens);
        $this->assertNull($response->stopReason);
        $this->assertSame([], $response->toolCalls);
    }

    public function test_has_tool_calls_returns_false_when_empty(): void
    {
        $response = new AIResponse('content', 10, 20);

        $this->assertFalse($response->hasToolCalls());
    }

    public function test_has_tool_calls_returns_true_when_present(): void
    {
        $response = new AIResponse('content', 10, 20, toolCalls: [['name' => 'read_file']]);

        $this->assertTrue($response->hasToolCalls());
    }

    public function test_total_tokens_sums_input_and_output(): void
    {
        $response = new AIResponse('content', 100, 50);

        $this->assertSame(150, $response->totalTokens());
    }

    public function test_stop_reason_is_set_when_provided(): void
    {
        $response = new AIResponse('content', 10, 20, stopReason: 'end_turn');

        $this->assertSame('end_turn', $response->stopReason);
    }
}
