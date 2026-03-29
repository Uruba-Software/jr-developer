<?php

namespace App\Contracts;

use App\DTOs\AIResponse;

interface AIProvider
{
    /**
     * Send messages and get a complete response.
     *
     * @param  array<array{role: string, content: string}>  $messages
     * @param  array<array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     */
    public function complete(array $messages, array $tools = []): AIResponse;

    /**
     * Stream a response, invoking the callback for each chunk.
     *
     * @param  array<array{role: string, content: string}>  $messages
     */
    public function stream(array $messages, callable $onChunk): void;
}
