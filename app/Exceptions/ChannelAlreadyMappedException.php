<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * T27 — Thrown when attempting to map a channel that is already bound to another project.
 */
class ChannelAlreadyMappedException extends RuntimeException
{
    public function __construct(
        public readonly string $platform,
        public readonly string $channelId,
        public readonly string $projectName,
    ) {
        parent::__construct(
            "Channel {$channelId} on {$platform} is already mapped to project \"{$projectName}\"."
        );
    }
}
