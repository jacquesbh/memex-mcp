<?php

declare(strict_types=1);

namespace Memex\Tool\Executor;

use Memex\Service\ContextService;
use Symfony\AI\McpSdk\Capability\Tool\IdentifierInterface;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;

class ListContextsToolExecutor implements ToolExecutorInterface, IdentifierInterface
{
    public function __construct(
        private readonly ContextService $contextService
    ) {}

    public function getName(): string
    {
        return 'list_contexts';
    }

    public function call(ToolCall $input): ToolCallResult
    {
        try {
            $contexts = $this->contextService->list();
            
            return new ToolCallResult(
                json_encode([
                    'success' => true,
                    'how_to_display' => 'Display the list of contexts in a readable format, including (important) their names and slugs.',
                    'what_to_do_next' => 'Use the "get_context" tool to retrieve a specific context by its slug or name.',
                    'total' => count($contexts),
                    'contexts' => $contexts,
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
