<?php

namespace App\Contracts;

use App\DTOs\ToolResult;
use App\Enums\ToolPermission;

interface ToolRunner
{
    /**
     * Whether this runner handles the given tool name.
     */
    public function supports(string $tool): bool;

    /**
     * Execute the tool with the given parameters.
     *
     * @param  array<string, mixed>  $params
     */
    public function run(string $tool, array $params): ToolResult;

    /**
     * The minimum permission level required to run this tool.
     */
    public function permission(): ToolPermission;
}
