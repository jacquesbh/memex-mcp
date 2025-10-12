<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\ContextService;
use Symfony\AI\McpSdk\Capability\Tool\IdentifierInterface;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;

class GetContextToolExecutor implements ToolExecutorInterface, IdentifierInterface
{
    public function __construct(
        private readonly ContextService $contextService
    ) {}

    public function getName(): string
    {
        return 'get_context';
    }

    public function call(ToolCall $input): ToolCallResult
    {
        try {
            $context = $this->contextService->get($input->arguments['uuid']);
            
            return new ToolCallResult(
                json_encode([
                    'success' => true,
                    'uuid' => $context['metadata']['uuid'] ?? null,
                    'name' => $context['name'],
                    'metadata' => $context['metadata'],
                    'content' => $context['content'],
                    'sections' => $context['sections'] ?? [],
                ], JSON_THROW_ON_ERROR)
            );
        } catch (\RuntimeException $e) {
            return new ToolCallResult(
                json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], JSON_THROW_ON_ERROR),
                isError: true
            );
        }
    }
}
