<?php

namespace App\Exceptions;

use RuntimeException;

class ToolNotFoundException extends RuntimeException
{
    public function __construct(string $tool)
    {
        parent::__construct("Tool not found: [{$tool}]");
    }
}
