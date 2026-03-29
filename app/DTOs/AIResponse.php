<?php

namespace App\DTOs;

readonly class AIResponse
{
    public function __construct(
        public string  $content,
        public int     $inputTokens,
        public int     $outputTokens,
        public ?string $stopReason = null,
        public array   $toolCalls = [],
    ) {}

    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
