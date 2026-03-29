<?php

namespace Tests\Unit\DTOs;

use App\DTOs\ToolResult;
use Tests\TestCase;

class ToolResultTest extends TestCase
{
    public function test_ok_creates_successful_result(): void
    {
        $result = ToolResult::ok('file contents');

        $this->assertTrue($result->success);
        $this->assertSame('file contents', $result->output);
        $this->assertNull($result->error);
    }

    public function test_fail_creates_failed_result(): void
    {
        $result = ToolResult::fail('File not found');

        $this->assertFalse($result->success);
        $this->assertNull($result->output);
        $this->assertSame('File not found', $result->error);
    }

    public function test_ok_accepts_array_output(): void
    {
        $result = ToolResult::ok(['line1', 'line2']);

        $this->assertTrue($result->success);
        $this->assertSame(['line1', 'line2'], $result->output);
    }

    public function test_ok_accepts_null_output(): void
    {
        $result = ToolResult::ok(null);

        $this->assertTrue($result->success);
        $this->assertNull($result->output);
    }
}
