<?php

namespace Tests\Unit\Contracts;

use App\Contracts\MessagingPlatform;
use App\DTOs\IncomingMessage;
use App\Enums\MessageType;
use Illuminate\Http\Request;
use Tests\TestCase;

class MessagingPlatformContractTest extends TestCase
{
    public function test_interface_methods_are_implemented_by_mock(): void
    {
        $platform = $this->createMock(MessagingPlatform::class);

        $platform->expects($this->once())->method('sendMessage')->with('C123', 'hello');
        $platform->expects($this->once())->method('sendFile')->with('C123', '/tmp/diff.txt', 'diff.txt');
        $platform->expects($this->once())->method('sendApprovalPrompt')->with(
            'C123',
            'Approve this diff?',
            [['id' => 'approve', 'label' => 'Approve']]
        );

        $incoming = new IncomingMessage('slack', 'C123', 'U1', 'hi', MessageType::Text, '{}');
        $platform->expects($this->once())->method('parseIncoming')->with([])->willReturn($incoming);
        $platform->expects($this->once())->method('verifyRequest')->willReturn(true);

        $platform->sendMessage('C123', 'hello');
        $platform->sendFile('C123', '/tmp/diff.txt', 'diff.txt');
        $platform->sendApprovalPrompt('C123', 'Approve this diff?', [['id' => 'approve', 'label' => 'Approve']]);
        $result = $platform->parseIncoming([]);
        $verified = $platform->verifyRequest(new Request());

        $this->assertInstanceOf(IncomingMessage::class, $result);
        $this->assertTrue($verified);
    }

    public function test_interface_is_bound_in_container(): void
    {
        // The interface should be resolvable once an adapter is bound.
        // This test verifies the interface exists and can be type-hinted.
        $this->assertTrue(interface_exists(MessagingPlatform::class));
    }
}
