<?php

namespace App\DTOs;

readonly class ToolResult
{
    public function __construct(
        public bool    $success,
        public mixed   $output,
        public ?string $error = null,
    ) {}

    public static function ok(mixed $output): self
    {
        return new self(success: true, output: $output);
    }

    public static function fail(string $error): self
    {
        return new self(success: false, output: null, error: $error);
    }
}
