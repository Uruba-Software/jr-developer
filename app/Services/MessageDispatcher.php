<?php

namespace App\Services;

use App\Contracts\MessagingPlatform;

class MessageDispatcher
{
    /**
     * Slack's max message length.
     * Discord allows 2000 but we use a safe common limit.
     */
    private const CHUNK_SIZE = 3000;

    public function __construct(
        private readonly MessagingPlatform $platform,
    ) {}

    /**
     * Send a message, splitting it into chunks if it exceeds the platform limit.
     */
    public function send(string $channel, string $text): void
    {
        if (mb_strlen($text) <= self::CHUNK_SIZE) {
            $this->platform->sendMessage($channel, $text);
            return;
        }

        foreach ($this->chunk($text) as $part) {
            $this->platform->sendMessage($channel, $part);
        }
    }

    /**
     * Send long content (e.g. a diff or file) as a file attachment instead of
     * splitting it into chat messages.
     */
    public function sendAsFile(string $channel, string $content, string $filename): void
    {
        $tmpPath = $this->writeTempFile($content, $filename);

        try {
            $this->platform->sendFile($channel, $tmpPath, $filename);
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Decide automatically: send as text chunks or as a file upload.
     * Content longer than 6000 chars is always sent as a file.
     */
    public function sendSmart(string $channel, string $text, string $filename = 'output.txt'): void
    {
        if (mb_strlen($text) > 6000) {
            $this->sendAsFile($channel, $text, $filename);
            return;
        }

        $this->send($channel, $text);
    }

    /**
     * Split text into chunks, trying to break at newlines to avoid cutting mid-word.
     *
     * @return string[]
     */
    public function chunk(string $text): array
    {
        if (mb_strlen($text) <= self::CHUNK_SIZE) {
            return [$text];
        }

        $chunks   = [];
        $remaining = $text;

        while (mb_strlen($remaining) > self::CHUNK_SIZE) {
            $slice     = mb_substr($remaining, 0, self::CHUNK_SIZE);
            $breakAt   = mb_strrpos($slice, "\n");

            if ($breakAt === false || $breakAt < self::CHUNK_SIZE / 2) {
                $breakAt = self::CHUNK_SIZE;
            }

            $chunks[]  = mb_substr($remaining, 0, $breakAt);
            $remaining = mb_ltrim(mb_substr($remaining, $breakAt));
        }

        if (mb_strlen($remaining) > 0) {
            $chunks[] = $remaining;
        }

        return $chunks;
    }

    private function writeTempFile(string $content, string $filename): string
    {
        $ext  = pathinfo($filename, PATHINFO_EXTENSION);
        $path = tempnam(sys_get_temp_dir(), 'jr_dispatch_') . ($ext ? '.' . $ext : '');
        file_put_contents($path, $content);
        return $path;
    }
}
