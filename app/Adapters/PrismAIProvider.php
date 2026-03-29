<?php

namespace App\Adapters;

use App\Contracts\AIProvider;
use App\DTOs\AIResponse;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class PrismAIProvider implements AIProvider
{
    private string $provider;
    private string $model;

    public function __construct()
    {
        $this->provider = config('jr.ai.default_provider', 'anthropic');
        $this->model    = config('jr.ai.default_model', 'claude-haiku-4-5-20251001');
    }

    public function complete(array $messages, array $tools = []): AIResponse
    {
        $request = Prism::text()
            ->using($this->provider, $this->model)
            ->withMessages($this->buildMessages($messages));

        if (!empty($tools)) {
            $request = $request->withTools($this->buildTools($tools));
        }

        $response = $request->asText();

        return new AIResponse(
            content:      $response->text,
            inputTokens:  $response->usage->promptTokens,
            outputTokens: $response->usage->completionTokens,
            stopReason:   $response->finishReason->value,
            toolCalls:    $this->extractToolCalls($response->toolCalls),
        );
    }

    public function stream(array $messages, callable $onChunk): void
    {
        $response = Prism::text()
            ->using($this->provider, $this->model)
            ->withMessages($this->buildMessages($messages))
            ->asStream();

        foreach ($response as $chunk) {
            $onChunk($chunk->text ?? '');
        }
    }

    /**
     * Override provider and model for this request.
     */
    public function withModel(string $provider, string $model): static
    {
        $clone           = clone $this;
        $clone->provider = $provider;
        $clone->model    = $model;

        return $clone;
    }

    /**
     * Route to a specific model tier based on task complexity.
     * 'fast'   → Haiku (cheap, quick)
     * 'smart'  → Sonnet (balanced)
     */
    public function forTier(string $tier): static
    {
        $models = [
            'fast'  => ['anthropic', 'claude-haiku-4-5-20251001'],
            'smart' => ['anthropic', 'claude-sonnet-4-6'],
        ];

        [$provider, $model] = $models[$tier] ?? $models['fast'];

        return $this->withModel($provider, $model);
    }

    /**
     * @param  array<array{role: string, content: string}>  $messages
     * @return array<UserMessage|AssistantMessage|SystemMessage>
     */
    private function buildMessages(array $messages): array
    {
        return array_map(function (array $msg) {
            return match ($msg['role']) {
                'system'    => new SystemMessage($msg['content']),
                'assistant' => new AssistantMessage($msg['content']),
                default     => new UserMessage($msg['content']),
            };
        }, $messages);
    }

    /**
     * @param  array<array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     * @return array<\Prism\Prism\Tool>
     */
    private function buildTools(array $tools): array
    {
        return array_map(function (array $tool) {
            $prismTool = new \Prism\Prism\Tool();
            $prismTool
                ->as($tool['name'])
                ->for($tool['description'] ?? '');

            foreach ($tool['parameters']['properties'] ?? [] as $name => $schema) {
                $required    = in_array($name, $tool['parameters']['required'] ?? []);
                $description = $schema['description'] ?? '';
                $type        = $schema['type'] ?? 'string';

                match ($type) {
                    'number', 'integer' => $prismTool->withNumberParameter($name, $description, $required),
                    'boolean'           => $prismTool->withBooleanParameter($name, $description, $required),
                    default             => $prismTool->withStringParameter($name, $description, $required),
                };
            }

            return $prismTool;
        }, $tools);
    }

    private function extractToolCalls(array $toolCalls): array
    {
        return array_map(fn ($call) => [
            'name'      => $call->name,
            'arguments' => $call->arguments(),
        ], $toolCalls);
    }
}
