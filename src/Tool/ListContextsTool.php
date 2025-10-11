<?php

declare(strict_types=1);

namespace Memex\Tool;

use Memex\Service\ContextService;
use PhpMcp\Server\Attributes\McpTool;

class ListContextsTool
{
    public function __construct(
        private readonly ContextService $contextService
    ) {}

    #[McpTool(
        name: 'list_contexts',
        description: 'List all available contexts in the knowledge base'
    )]
    public function list(): array
    {
        try {
            $contexts = $this->contextService->list();
            
            return [
                'success' => true,
                'total' => count($contexts),
                'contexts' => $contexts,
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
