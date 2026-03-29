<?php

namespace App\Enums;

enum MessagePlatform: string
{
    case Slack   = 'slack';
    case Discord = 'discord';

    public function label(): string
    {
        return match($this) {
            self::Slack   => 'Slack',
            self::Discord => 'Discord',
        };
    }
}
