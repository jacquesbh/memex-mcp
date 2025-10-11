<?php

declare(strict_types=1);

namespace Memex\Tool;

use Memex\Service\ContextService;
use PhpMcp\Server\Attributes\McpTool;

class GetContextTool
{
    public function __construct(
        private readonly ContextService $contextService
    ) {}

    #[McpTool(
        name: 'get_context',
        description: 'Retrieve a context (prompt/persona/conventions) from the knowledge base using search query'
    )]
    public function get(string $query): array
    {
        try {
            $context = $this->contextService->get($query);
            
            return [
                'success' => true,
                'name' => $context['name'],
                'metadata' => $context['metadata'],
                'content' => $context['content'],
                'sections' => $context['sections'] ?? [],
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
