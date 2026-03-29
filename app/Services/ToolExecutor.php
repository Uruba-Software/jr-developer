<?php

namespace App\Services;

use App\DTOs\ToolResult;
use App\Enums\ToolPermission;
use App\Exceptions\ToolNotFoundException;
use App\Exceptions\ToolPermissionDeniedException;
use Illuminate\Support\Facades\Log;

class ToolExecutor
{
    /**
     * Permissions that are auto-approved without user confirmation.
     */
    private const AUTO_APPROVED = [
        ToolPermission::Read,
    ];

    /**
     * Permissions that are always blocked.
     */
    private const ALWAYS_BLOCKED = [
        ToolPermission::Destroy,
    ];

    public function __construct(
        private readonly ToolRegistry $registry,
    ) {}

    /**
     * Execute a tool. Throws on permission denial or if tool is not found.
     *
     * @param  array<string, mixed>  $params
     * @param  ToolPermission[]      $grantedPermissions  Permissions the caller has approved
     */
    public function execute(
        string $tool,
        array $params = [],
        array $grantedPermissions = [],
    ): ToolResult {
        $runner     = $this->registry->find($tool);
        $permission = $runner->permission();

        // Always blocked
        if (in_array($permission, self::ALWAYS_BLOCKED)) {
            throw new ToolPermissionDeniedException($tool, $permission);
        }

        // Requires explicit grant (not auto-approved and not in granted list)
        if (
            !in_array($permission, self::AUTO_APPROVED) &&
            !in_array($permission, $grantedPermissions)
        ) {
            throw new ToolPermissionDeniedException($tool, $permission);
        }

        Log::debug("ToolExecutor: running [{$tool}]", ['permission' => $permission->value]);

        return $runner->run($tool, $params);
    }

    /**
     * Check if a tool can run without user approval.
     */
    public function isAutoApproved(string $tool): bool
    {
        if (!$this->registry->has($tool)) {
            return false;
        }

        $runner = $this->registry->find($tool);

        return in_array($runner->permission(), self::AUTO_APPROVED);
    }

    /**
     * Check if a tool is permanently blocked.
     */
    public function isBlocked(string $tool): bool
    {
        if (!$this->registry->has($tool)) {
            return false;
        }

        $runner = $this->registry->find($tool);

        return in_array($runner->permission(), self::ALWAYS_BLOCKED);
    }
}
