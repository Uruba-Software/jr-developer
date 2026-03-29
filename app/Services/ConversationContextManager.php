<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ConversationContextManager
{
    /**
     * Estimated chars-per-token ratio for pruning heuristic.
     * Conservative estimate: 1 token ≈ 3.5 chars (English prose).
     */
    private const CHARS_PER_TOKEN = 3.5;

    private int $maxTokens;
    private int $ttlSeconds;

    public function __construct()
    {
        $this->maxTokens  = config('jr.context.max_tokens', 100_000);
        $this->ttlSeconds = config('jr.context.ttl_seconds', 86_400);
    }

    /**
     * Append a message to the conversation context.
     *
     * @param  array{role: string, content: string}  $message
     */
    public function append(string $conversationId, array $message): void
    {
        $messages   = $this->get($conversationId);
        $messages[] = $message;
        $messages   = $this->prune($messages);

        $this->store($conversationId, $messages);
    }

    /**
     * Get all messages for a conversation.
     *
     * @return array<array{role: string, content: string}>
     */
    public function get(string $conversationId): array
    {
        return Cache::get($this->key($conversationId), []);
    }

    /**
     * Replace the entire context with a new set of messages.
     *
     * @param  array<array{role: string, content: string}>  $messages
     */
    public function set(string $conversationId, array $messages): void
    {
        $this->store($conversationId, $this->prune($messages));
    }

    /**
     * Delete the context for a conversation.
     */
    public function forget(string $conversationId): void
    {
        Cache::forget($this->key($conversationId));
    }

    /**
     * Count messages in context.
     */
    public function count(string $conversationId): int
    {
        return count($this->get($conversationId));
    }

    /**
     * Estimate total token usage for the context.
     */
    public function estimatedTokens(string $conversationId): int
    {
        return $this->estimateTokens($this->get($conversationId));
    }

    /**
     * Prune oldest messages (excluding system messages) until within token limit.
     *
     * Strategy: keep all system messages at the front, prune oldest user/assistant pairs.
     *
     * @param  array<array{role: string, content: string}>  $messages
     * @return array<array{role: string, content: string}>
     */
    public function prune(array $messages): array
    {
        if ($this->estimateTokens($messages) <= $this->maxTokens) {
            return $messages;
        }

        [$systemMessages, $conversationMessages] = $this->splitSystemMessages($messages);

        while (
            count($conversationMessages) > 0 &&
            $this->estimateTokens(array_merge($systemMessages, $conversationMessages)) > $this->maxTokens
        ) {
            array_shift($conversationMessages);
        }

        return array_merge($systemMessages, $conversationMessages);
    }

    /**
     * @param  array<array{role: string, content: string}>  $messages
     * @return array{array<array{role: string, content: string}>, array<array{role: string, content: string}>}
     */
    private function splitSystemMessages(array $messages): array
    {
        $system       = [];
        $conversation = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $system[] = $message;
            } else {
                $conversation[] = $message;
            }
        }

        return [$system, $conversation];
    }

    /**
     * @param  array<array{role: string, content: string}>  $messages
     */
    private function estimateTokens(array $messages): int
    {
        $totalChars = 0;

        foreach ($messages as $message) {
            $totalChars += mb_strlen($message['content'] ?? '');
        }

        return (int) ceil($totalChars / self::CHARS_PER_TOKEN);
    }

    /**
     * @param  array<array{role: string, content: string}>  $messages
     */
    private function store(string $conversationId, array $messages): void
    {
        Cache::put($this->key($conversationId), $messages, $this->ttlSeconds);
    }

    private function key(string $conversationId): string
    {
        return "jr:context:{$conversationId}";
    }
}
