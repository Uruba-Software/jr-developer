<?php

namespace App\Enums;

enum ToolPermission: string
{
    case Read    = 'read';    // auto-allowed
    case Write   = 'write';   // requires approval
    case Exec    = 'exec';    // requires approval
    case Deploy  = 'deploy';  // explicit confirmation
    case Destroy = 'destroy'; // always blocked

    public function requiresApproval(): bool
    {
        return match($this) {
            self::Read    => false,
            self::Write   => true,
            self::Exec    => true,
            self::Deploy  => true,
            self::Destroy => true,
        };
    }

    public function isBlocked(): bool
    {
        return $this === self::Destroy;
    }
}
