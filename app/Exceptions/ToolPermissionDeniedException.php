<?php

namespace App\Exceptions;

use App\Enums\ToolPermission;
use RuntimeException;

class ToolPermissionDeniedException extends RuntimeException
{
    public function __construct(string $tool, ToolPermission $required)
    {
        parent::__construct(
            "Permission denied for tool [{$tool}]: requires [{$required->value}] permission"
        );
    }
}
