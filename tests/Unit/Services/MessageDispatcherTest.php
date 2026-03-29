<?php

namespace Tests\Unit\Services;

use App\Contracts\MessagingPlatform;
use App\Services\MessageDispatcher;
use Mockery;
use Tests\TestCase;

class MessageDispatcherTest extends TestCase
{
    private MessagingPlatform $platform;
    private MessageDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform   = Mockery::mock(MessagingPlatform::class);
        $this->dispatcher = new MessageDispatcher($this->platform);
    }

    // -------------------------------------------------------------------------
    // send — short message (no chunking)
    // -------------------------------------------------------------------------

    public function test_send_short_message_sends_once(): void
    {
        $this->platform->shouldReceive('sendMessage')
            ->once()
            ->with('C123', 'short text');

        $this->dispatcher->send('C123', 'short text');
    }

    // -------------------------------------------------------------------------
    // send — long message (chunked)
    // -------------------------------------------------------------------------

    public function test_send_long_message_splits_into_chunks(): void
    {
        $text = str_repeat('a', 9000); // 3 chunks of 3000

        $this->platform->shouldReceive('sendMessage')
            ->times(3)
            ->with('C123', Mockery::type('string'));

        $this->dispatcher->send('C123', $text);
    }

    public function test_send_message_exactly_at_limit_sends_once(): void
    {
        $text = str_repeat('a', 3000);

        $this->platform->shouldReceive('sendMessage')
            ->once()
            ->with('C123', $text);

        $this->dispatcher->send('C123', $text);
    }

    // -------------------------------------------------------------------------
    // chunk
    // -------------------------------------------------------------------------

    public function test_chunk_returns_single_item_for_short_text(): void
    {
        $chunks = $this->dispatcher->chunk('hello');

        $this->assertCount(1, $chunks);
        $this->assertSame('hello', $chunks[0]);
    }

    public function test_chunk_breaks_on_newlines_when_possible(): void
    {
        $line  = str_repeat('a', 1500);
        $text  = $line . "\n" . $line . "\n" . $line . "\n" . $line;

        $chunks = $this->dispatcher->chunk($text);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(3000, mb_strlen($chunk));
        }
    }

    public function test_chunk_preserves_all_content(): void
    {
        $text   = str_repeat('x', 7500);
        $chunks = $this->dispatcher->chunk($text);

        $this->assertSame($text, implode('', $chunks));
    }

    public function test_chunk_handles_empty_string(): void
    {
        $chunks = $this->dispatcher->chunk('');

        $this->assertCount(1, $chunks);
        $this->assertSame('', $chunks[0]);
    }

    // -------------------------------------------------------------------------
    // sendAsFile
    // -------------------------------------------------------------------------

    public function test_send_as_file_uploads_temp_file(): void
    {
        $this->platform->shouldReceive('sendFile')
            ->once()
            ->with('C123', Mockery::type('string'), 'diff.txt');

        $this->dispatcher->sendAsFile('C123', 'file content here', 'diff.txt');
    }

    public function test_send_as_file_cleans_up_temp_file_after_upload(): void
    {
        $capturedPath = null;

        $this->platform->shouldReceive('sendFile')
            ->once()
            ->andReturnUsing(function (string $channel, string $path, string $name) use (&$capturedPath) {
                $capturedPath = $path;
            });

        $this->dispatcher->sendAsFile('C123', 'content', 'file.txt');

        $this->assertNotNull($capturedPath);
        $this->assertFileDoesNotExist($capturedPath);
    }

    public function test_send_as_file_cleans_up_even_when_upload_throws(): void
    {
        $capturedPath = null;

        $this->platform->shouldReceive('sendFile')
            ->once()
            ->andReturnUsing(function (string $channel, string $path, string $name) use (&$capturedPath) {
                $capturedPath = $path;
                throw new \RuntimeException('Upload failed');
            });

        try {
            $this->dispatcher->sendAsFile('C123', 'content', 'file.txt');
        } catch (\RuntimeException) {}

        $this->assertFileDoesNotExist($capturedPath);
    }

    // -------------------------------------------------------------------------
    // sendSmart
    // -------------------------------------------------------------------------

    public function test_send_smart_sends_short_text_as_message(): void
    {
        $this->platform->shouldReceive('sendMessage')
            ->once()
            ->with('C123', 'short');

        $this->dispatcher->sendSmart('C123', 'short');
    }

    public function test_send_smart_sends_very_long_text_as_file(): void
    {
        $text = str_repeat('a', 6001);

        $this->platform->shouldReceive('sendFile')
            ->once()
            ->with('C123', Mockery::type('string'), 'output.txt');

        $this->dispatcher->sendSmart('C123', $text);
    }

    public function test_send_smart_uses_custom_filename_for_file_upload(): void
    {
        $text = str_repeat('a', 7000);

        $this->platform->shouldReceive('sendFile')
            ->once()
            ->with('C123', Mockery::type('string'), 'diff.patch');

        $this->dispatcher->sendSmart('C123', $text, 'diff.patch');
    }
}
