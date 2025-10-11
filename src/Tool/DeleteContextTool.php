<?php

declare(strict_types=1);

namespace Memex\Tool;

use Memex\Service\ContextService;
use PhpMcp\Server\Attributes\McpTool;

class DeleteContextTool
{
    public function __construct(
        private readonly ContextService $contextService
    ) {}

    #[McpTool(
        name: 'delete_context',
        description: 'Delete a context from the knowledge base'
    )]
    public function delete(string $slug): array
    {
        try {
            $result = $this->contextService->delete($slug);
            
            return [
                'success' => true,
                'title' => $result['title'],
                'slug' => $result['slug'],
                'type' => $result['type'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
