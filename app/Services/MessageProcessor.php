<?php

namespace App\Services;

use App\Contracts\AIProvider;
use App\Contracts\MessagingPlatform;
use App\DTOs\IncomingMessage;
use App\Enums\OperatingMode;
use App\Enums\ToolPermission;
use App\Exceptions\ToolPermissionDeniedException;
use Illuminate\Support\Facades\Log;

class MessageProcessor
{
    /**
     * Max tool call rounds per turn to prevent infinite loops.
     */
    private const MAX_TOOL_ROUNDS = 5;

    public function __construct(
        private readonly ConversationContextManager $context,
        private readonly AIProvider                 $ai,
        private readonly ToolExecutor               $tools,
        private readonly MessagingPlatform          $platform,
        private readonly MessageDispatcher          $dispatcher,
    ) {}

    /**
     * Process an incoming message according to the operating mode.
     */
    public function handle(IncomingMessage $message, OperatingMode $mode): void
    {
        match ($mode) {
            OperatingMode::Manual => $this->handleManual($message),
            OperatingMode::Agent,
            OperatingMode::Cloud  => $this->handleAgent($message),
        };
    }

    // -------------------------------------------------------------------------
    // Manual mode — no AI, just acknowledge
    // -------------------------------------------------------------------------

    private function handleManual(IncomingMessage $message): void
    {
        Log::info('MessageProcessor [manual]', [
            'channel' => $message->channelId,
            'user'    => $message->userId,
            'text'    => mb_substr($message->text, 0, 200),
        ]);

        $this->platform->sendMessage(
            $message->channelId,
            '_Manual mode: message received but AI is disabled._'
        );
    }

    // -------------------------------------------------------------------------
    // Agent mode — full AI loop with tool execution
    // -------------------------------------------------------------------------

    private function handleAgent(IncomingMessage $message): void
    {
        $conversationId = $this->conversationId($message);

        // Append user message to context
        $this->context->append($conversationId, [
            'role'    => 'user',
            'content' => $message->text,
        ]);

        $messages  = $this->context->get($conversationId);
        $toolDefs  = $this->buildToolDefinitions();
        $rounds    = 0;

        // Agentic loop: run until AI stops calling tools or max rounds hit
        while ($rounds < self::MAX_TOOL_ROUNDS) {
            $response = $this->ai->complete($messages, $toolDefs);

            $rounds++;

            if (!$response->hasToolCalls()) {
                // Final response — store and send
                $this->context->append($conversationId, [
                    'role'    => 'assistant',
                    'content' => $response->content,
                ]);

                $this->dispatcher->sendSmart(
                    $message->channelId,
                    $response->content,
                    'response.txt'
                );

                return;
            }

            // Execute tool calls and feed results back
            $toolResults = $this->executeToolCalls($response->toolCalls, $message->channelId);

            $messages[] = ['role' => 'assistant', 'content' => $response->content];
            $messages[] = ['role' => 'user',      'content' => $this->formatToolResults($toolResults)];
        }

        // Exceeded max rounds
        $this->platform->sendMessage(
            $message->channelId,
            '_Agent reached max tool call limit. Please try a more specific request._'
        );
    }

    /**
     * @param  array<array{name: string, arguments: array<string, mixed>}>  $toolCalls
     * @return array<array{tool: string, success: bool, output: mixed, error: ?string}>
     */
    private function executeToolCalls(array $toolCalls, string $channel): array
    {
        $results = [];

        foreach ($toolCalls as $call) {
            $toolName = $call['name'];

            // Check if tool needs approval before running
            if (!$this->tools->isAutoApproved($toolName) && !$this->tools->isBlocked($toolName)) {
                // Send approval prompt for write/exec/deploy tools
                $this->platform->sendApprovalPrompt(
                    $channel,
                    "AI wants to run `{$toolName}`. Allow?",
                    [
                        ['id' => "approve:{$toolName}", 'label' => 'Approve', 'style' => 'primary'],
                        ['id' => "reject:{$toolName}",  'label' => 'Reject',  'style' => 'danger'],
                    ]
                );

                // For now, block and report — approval flow is async (handled separately)
                $results[] = [
                    'tool'    => $toolName,
                    'success' => false,
                    'output'  => null,
                    'error'   => 'Awaiting user approval.',
                ];
                continue;
            }

            try {
                $result    = $this->tools->execute($toolName, $call['arguments'] ?? []);
                $results[] = [
                    'tool'    => $toolName,
                    'success' => $result->success,
                    'output'  => $result->output,
                    'error'   => $result->error,
                ];
            } catch (ToolPermissionDeniedException $e) {
                $results[] = [
                    'tool'    => $toolName,
                    'success' => false,
                    'output'  => null,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function formatToolResults(array $results): string
    {
        $lines = [];

        foreach ($results as $result) {
            if ($result['success']) {
                $output  = is_string($result['output'])
                    ? $result['output']
                    : json_encode($result['output']);
                $lines[] = "[Tool: {$result['tool']}]\n{$output}";
            } else {
                $lines[] = "[Tool: {$result['tool']}] ERROR: {$result['error']}";
            }
        }

        return implode("\n\n", $lines);
    }

    /**
     * Build tool definitions from registered runners.
     * Runners expose their name via a static NAME constant if available,
     * otherwise we derive it from the class basename.
     */
    private function buildToolDefinitions(): array
    {
        return array_map(function ($runner) {
            $name = defined(get_class($runner) . '::NAME')
                ? constant(get_class($runner) . '::NAME')
                : strtolower(class_basename(get_class($runner)));

            return [
                'name'        => $name,
                'description' => '',
                'parameters'  => ['properties' => [], 'required' => []],
            ];
        }, $this->tools->registry()->all());
    }

    private function conversationId(IncomingMessage $message): string
    {
        return "{$message->platform}:{$message->channelId}";
    }
}
