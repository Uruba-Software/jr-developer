<?php

namespace App\Contracts;

use App\DTOs\TestResult;

/**
 * T21 — TestRunner contract
 *
 * Implemented by PHP/Laravel, Node.js, and Python adapters.
 */
interface TestRunner
{
    /**
     * Run the test suite for the given project path.
     *
     * @param  string       $projectPath  Absolute path to the project root
     * @param  string|null  $filter       Optional test filter (class name, method, keyword)
     */
    public function run(string $projectPath, ?string $filter = null): TestResult;

    /**
     * Whether this runner supports the project at the given path.
     * Used by auto-detection based on project type.
     */
    public function supports(string $projectPath): bool;

    /**
     * Human-readable name of this adapter (e.g. "Laravel PHPUnit").
     */
    public function name(): string;
}
