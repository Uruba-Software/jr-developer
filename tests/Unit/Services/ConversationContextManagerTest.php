<?php

namespace Tests\Unit\Services;

use App\Services\ConversationContextManager;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConversationContextManagerTest extends TestCase
{
    private ConversationContextManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jr.context.max_tokens'  => 1000,
            'jr.context.ttl_seconds' => 3600,
            'cache.default'          => 'array',
        ]);

        $this->manager = new ConversationContextManager();
    }

    private function msg(string $role, string $content): array
    {
        return ['role' => $role, 'content' => $content];
    }

    // -------------------------------------------------------------------------
    // append / get
    // -------------------------------------------------------------------------

    public function test_get_returns_empty_array_for_new_conversation(): void
    {
        $messages = $this->manager->get('conv-001');

        $this->assertSame([], $messages);
    }

    public function test_append_adds_messages_to_context(): void
    {
        $this->manager->append('conv-001', $this->msg('user', 'Hello'));
        $this->manager->append('conv-001', $this->msg('assistant', 'Hi there'));

        $messages = $this->manager->get('conv-001');

        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('assistant', $messages[1]['role']);
    }

    public function test_append_preserves_message_order(): void
    {
        $this->manager->append('conv-001', $this->msg('user', 'First'));
        $this->manager->append('conv-001', $this->msg('assistant', 'Second'));
        $this->manager->append('conv-001', $this->msg('user', 'Third'));

        $messages = $this->manager->get('conv-001');

        $this->assertSame('First', $messages[0]['content']);
        $this->assertSame('Second', $messages[1]['content']);
        $this->assertSame('Third', $messages[2]['content']);
    }

    // -------------------------------------------------------------------------
    // set
    // -------------------------------------------------------------------------

    public function test_set_replaces_existing_context(): void
    {
        $this->manager->append('conv-001', $this->msg('user', 'old message'));

        $this->manager->set('conv-001', [
            $this->msg('user', 'new message 1'),
            $this->msg('assistant', 'new message 2'),
        ]);

        $messages = $this->manager->get('conv-001');

        $this->assertCount(2, $messages);
        $this->assertSame('new message 1', $messages[0]['content']);
    }

    // -------------------------------------------------------------------------
    // forget
    // -------------------------------------------------------------------------

    public function test_forget_clears_context(): void
    {
        $this->manager->append('conv-001', $this->msg('user', 'Hello'));
        $this->manager->forget('conv-001');

        $this->assertSame([], $this->manager->get('conv-001'));
    }

    // -------------------------------------------------------------------------
    // count / estimatedTokens
    // -------------------------------------------------------------------------

    public function test_count_returns_message_count(): void
    {
        $this->manager->append('conv-001', $this->msg('user', 'Hello'));
        $this->manager->append('conv-001', $this->msg('assistant', 'Hi'));

        $this->assertSame(2, $this->manager->count('conv-001'));
    }

    public function test_count_returns_zero_for_empty_context(): void
    {
        $this->assertSame(0, $this->manager->count('conv-999'));
    }

    public function test_estimated_tokens_returns_positive_value(): void
    {
        $this->manager->append('conv-001', $this->msg('user', str_repeat('word ', 100)));

        $this->assertGreaterThan(0, $this->manager->estimatedTokens('conv-001'));
    }

    public function test_estimated_tokens_returns_zero_for_empty_context(): void
    {
        $this->assertSame(0, $this->manager->estimatedTokens('conv-999'));
    }

    // -------------------------------------------------------------------------
    // prune — token limit
    // -------------------------------------------------------------------------

    public function test_prune_removes_oldest_messages_when_over_limit(): void
    {
        // Each message is ~1000 chars ≈ 286 tokens. Max is 1000 tokens → ~3-4 messages fit.
        $longContent = str_repeat('a', 1000);

        $messages = [
            $this->msg('user',      $longContent . '-1'),
            $this->msg('assistant', $longContent . '-2'),
            $this->msg('user',      $longContent . '-3'),
            $this->msg('assistant', $longContent . '-4'),
            $this->msg('user',      $longContent . '-5'),
        ];

        $pruned = $this->manager->prune($messages);

        $this->assertLessThan(count($messages), count($pruned));
    }

    public function test_prune_preserves_system_messages(): void
    {
        $systemMsg   = $this->msg('system', 'You are a helpful assistant.');
        $longContent = str_repeat('x', 1200);

        $messages = [
            $systemMsg,
            $this->msg('user',      $longContent . '-1'),
            $this->msg('assistant', $longContent . '-2'),
            $this->msg('user',      $longContent . '-3'),
        ];

        $pruned = $this->manager->prune($messages);

        $this->assertSame('system', $pruned[0]['role']);
        $this->assertSame('You are a helpful assistant.', $pruned[0]['content']);
    }

    public function test_prune_does_not_modify_messages_under_limit(): void
    {
        $messages = [
            $this->msg('user',      'Hello'),
            $this->msg('assistant', 'Hi'),
        ];

        $pruned = $this->manager->prune($messages);

        $this->assertCount(2, $pruned);
    }

    public function test_prune_handles_empty_messages(): void
    {
        $pruned = $this->manager->prune([]);

        $this->assertSame([], $pruned);
    }

    // -------------------------------------------------------------------------
    // isolation — different conversation IDs
    // -------------------------------------------------------------------------

    public function test_conversations_are_isolated(): void
    {
        $this->manager->append('conv-A', $this->msg('user', 'Hello from A'));
        $this->manager->append('conv-B', $this->msg('user', 'Hello from B'));

        $this->assertSame('Hello from A', $this->manager->get('conv-A')[0]['content']);
        $this->assertSame('Hello from B', $this->manager->get('conv-B')[0]['content']);
    }

    public function test_forget_only_clears_target_conversation(): void
    {
        $this->manager->append('conv-A', $this->msg('user', 'A message'));
        $this->manager->append('conv-B', $this->msg('user', 'B message'));

        $this->manager->forget('conv-A');

        $this->assertSame([], $this->manager->get('conv-A'));
        $this->assertCount(1, $this->manager->get('conv-B'));
    }
}
